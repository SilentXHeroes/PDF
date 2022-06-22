<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>PDF Merge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.css">
    <style type="text/css">
        * {
            padding: 0;
            margin: 0;
            display: inline-block;
        }

        script, head, style {
            display: none;
        }

        body, html {
            height: 100%;
            width: 100%;
            position: absolute;
        }

        #container {
            display: flex;
        }

        .file-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }
        .file-container:not(:first-child) {
            border-left: 2px solid black;
        }

        .file-container > form {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%,-50%);
        }
        .file-container > .pages {
            overflow: hidden;
        }
        .file-container > .pages > div {
            width: 100%;
            height: 100%;
        }
        .file-container > .pages, canvas {
            width: calc(793.7px * .7);
            height: calc(1122.5px * .7);
        }
        canvas {
            transform: scale(1);
            user-select: none;
            cursor: grab;
        }
        canvas.dragging {
            transform: scale(.3);
        }
        canvas:not(.dragging) {
            transition: left .3s, top .3s, transform .1s .2s;
        }
    </style>
</head>
<body>
    <div id="container">
        <div id="file1" class="file-container">
            <p>Fichier 1</p>
            <div class="pages"></div>
            <form id="form1"><input type="file" name="file"></form>
        </div>
        <div id="file2" class="file-container">
            <p>Fichier 2</p>
            <div class="pages"></div>
            <form id="form2"><input type="file" name="file"></form>
        </div>
        <div id="merge" class="file-container">
            <p>Rendu</p>
            <div class="pages"></div>
        </div>
    </div>
    <script type="text/javascript" src="jquery.js"></script>
    <script type="text/javascript" src="jquery-ui.min.js"></script>
    <script type="text/javascript" src="jspdf.umd.min.js"></script>
    <script type="text/javascript" src="html2canvas.js"></script>
	<script type="text/javascript" src="pdf.min.js"></script>
	<script type="text/javascript" src="pdf.worker.min.js"></script>
	<script type="text/javascript">
        let pdfCreator = new jspdf.jsPDF('p', "cm", "a4", false, false, 10);
        let numPages;
        let numPagesPrinted;
        let currentPageIndex;
        let rawData = {};
        let currentID;
        let fileContainer;
        let dragDom;
        let scale = 1.5;
        let xhr = new XMLHttpRequest();

        document.querySelectorAll("input[type=file]").forEach(function(thos) {
            thos.addEventListener("change", e => {
                fileContainer = e.target.parentNode.previousElementSibling;
                currentID = fileContainer.parentNode.id;

                xhr.open("POST", 'get.php');
                xhr.onloadend = function() {
                    rawData[currentID] = { raw: this.responseText, obj: null, pages: {} };
                    parseData(this.responseText);
                };
                xhr.send(new FormData(e.target.parentNode));
            });
        });

        function parseData(data) {
            numPages = 0;
            numPagesPrinted = 0;
            currentPageIndex = 0;

            let uIntArray = new Uint8Array(data.length).map((x,idx) => data.charCodeAt(idx));

            pdfjsLib.getDocument(uIntArray).then(pdf => {
                numPages = pdf.numPages;

                rawData[currentID].obj = pdf;

                getNextPage();
            });
        }

        function getNextPage(index = -1) {
            let useIndex;

            if(currentPageIndex !== null) {
                currentPageIndex++;
                useIndex = currentPageIndex;
            }
            else if(index !== -1) useIndex = index;

            rawData[currentID].obj.getPage(useIndex).then(page => {
                let divNode = document.createElement("div");
                divNode.className = "page";

                rawData[currentID].pages[useIndex] = page;

                divNode.appendChild(newCanvas(page));
                fileContainer.appendChild(divNode);
            });
        }

        function newCanvas(page, isCopy = false) {
            let viewport = page.getViewport(scale);
            let canvas = $("canvas");

            canvas.width = viewport.width;
            canvas.height = viewport.height;

            let ctx = canvas.getContext('2d');
            // ctx.filter = "grayscale(1)";
            let promise = page.render({
                    canvasContext: ctx,
                    viewport: viewport
                });

            canvas.draggable = true;
            canvas.addEventListener("dragstart", drag);
            canvas.addEventListener("drag", drag);
            canvas.addEventListener("dragend", drag);

            if(isCopy) canvas.classList.add("copy-canvas");
            else {
                promise.then(function() {
                        numPagesPrinted++;

                        if(numPages === numPagesPrinted) {
                            print();
                            currentPageIndex = null;
                            return;
                        }

                        if(currentPageIndex !== null) getNextPage();
                    });
            }

            return canvas;
        }

        print = function() {
            let canvas = document.getElementsByTagName("canvas");
            for(var i = 0; i < canvas.length; i++) {
                let dataURL = canvas[i].toDataURL("image/jpeg");

                pdfCreator.addImage(dataURL, 'JPEG', 0, 0, 21, 29.7);
                if(i < canvas.length - 1) pdfCreator.addPage();
            }
        };
		
        function drag(event) {
            if(event.type === "dragstart") {
                let img = new Image();

                img.width = 0;
                img.height = 0;
                img.opacity = 0;
                event.dataTransfer.setDragImage(img, 0, 0);

                dragDom = event.target;
                dragDom.classList.add("dragging");
                dragDom.style.position = "absolute";

                let pageParent = dragDom.parentNode;
                let fileID = pageParent.parentNode.parentNode.id;
                let pageIndex = $(pageParent).helper.index() + 1;

                dragDom.before(newCanvas(rawData[fileID].pages[pageIndex], true));
                document.body.appendChild(dragDom);

                dragDom.dataset.file = fileID;
                dragDom.dataset.page = pageIndex;
            }

            let left = event.clientX - dragDom.clientWidth / 2;
            let top = event.clientY - dragDom.clientHeight / 2;

            if(left > 0 || top > 0) {
                dragDom.style.left = left + "px";
                dragDom.style.top = top + "px";
            }

            console.log(event.type, event.clientX, event.clientY, dragDom.style.left, dragDom.style.top);

            if(event.type === "dragend") {
                let fileDOM = document.querySelector("#" + dragDom.dataset.file);
                let page = fileDOM.querySelectorAll(".pages .page")[dragDom.dataset.page - 1];
                let boundaries = page.getBoundingClientRect();

                dragDom.addEventListener("transitionend", e => {
                    if(e.propertyName === "transform" && ! e.target.classList.contains("dragging")) {
                        page.append(dragDom);

                        dragDom.style.position = "relative";
                        dragDom.style.left = 0;
                        dragDom.style.top = 0;
                        dragDom.previousSibling.remove();
                    }
                });

                dragDom.classList.remove("dragging");
                dragDom.style.left = boundaries.left + "px";
                dragDom.style.top = boundaries.top + "px";
            }
        }

        function $(element) {
            let node;

            if(element instanceof Node) {
                node = element;
            }
            else {
                node = document.createElement(element);
            }

            node.helper = {
                index: function() {
                    let i = -1;
                    let child = node;

                    while(child !== null) {
                        child = child.previousSibling;
                        i++;
                    }
                    return i;
                },
                parent: function() {
                    return node.parentNode;
                },
                parents: function(query = '') {
                    let parent = node.parentNode;
                    let nodes = query === '' ? [] : document.querySelectorAll(query);
                    let parentNodes = [];
                    let isEqual = false;

                    while(parent !== null) {
                        if(query === '') parentNodes.push(parent);

                        // On break si on tombe sur l'élément parent recherché
                        nodes.forEach(elem => {
                            if(isEqual) return;
                            isEqual = elem.isEqualNode(parent);
                            if(query !== '' && isEqual) parentNodes.push(parent);
                        });

                        parent = parent.parentNode;
                    }

                    return parentNodes;
                },
                data: function(...args) {
                    // Getter
                    if(args.length === 1) {
                        return this.dataset[args[0]];
                    }
                    // Setter
                    else if(typeof args[0] === "object") {
                        for(let key in args[0]) this.dataset[key] = parse(args[key]);
                    }
                    else {
                        this.dataset[args[0]] = parse(args[1]);
                    }

                    function parse(value) {
                        if(typeof value === "object") {
                            value = JSON.stringify(value);
                        }

                        return value;
                    }
                }
            };

            return node;
        }

	</script>
</body>
</html>