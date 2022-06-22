class pdf {
		
	constructor() {
		this.xhr = new XMLHttpRequest();
	}
	
	newInstance() {
		this.pdf = new jspdf.jsPDF('p', "cm", "a4", false, false, 10);
		this.pages = [];
	}
	
	get() {
		let thos = this;
		let formData = new FormData();
		formData.append("cmd", "print");
		formData.append("pdfURL", document.querySelector("#detail object").data);
		
		this.newInstance();
		
		this.xhr.open("POST", 'ajax.php');
		this.xhr.onloadend = function() {
			thos.setRawData(JSON.parse(this.responseText));
			thos.load();
		};
		this.xhr.send(formData);
	}
	
	setRawData(rawData) {
		this.rawData = rawData;
		this.newInstance();
	}
	
	load() {
		if(this.rawData.length == 0) return true;
		
		let raw = this.rawData.shift();		
		let uIntArray = new Uint8Array(raw.length).map((x,idx) => raw.charCodeAt(idx));
		let thos = this;
		
		this.currentPDF = null;
		this.numPages = 0;
		this.currentPageIndex = 0;
		this.numPagesPrinted = 0;

		pdfjsLib.getDocument(uIntArray).then(pdf => {
			thos.numPages = pdf.numPages;	
			thos.currentPDF = pdf;
			thos.nextPage();
		});
		
		return false;
	}

	nextPage(index = -1) {
		let thos = this;

		this.currentPageIndex++;
		this.currentPDF.getPage(this.currentPageIndex).then(page => {
			thos.pages.push(thos.getDataURL(page));
		});
	}

	getDataURL(page) {
		let thos = this;
		let viewport = page.getViewport(1.5);
		let canvas = document.createElement("canvas");
		canvas.className = "hidden";
		canvas.width = viewport.width;
		canvas.height = viewport.height;
		document.body.appendChild(canvas);

		let ctx = canvas.getContext('2d');
		page.render({
			canvasContext: ctx,
			viewport: viewport
		})
		.then(function() {
			thos.numPagesPrinted++;

			if(thos.numPages === thos.numPagesPrinted) {
				let isOver = thos.load();
				if(isOver) thos.print();
			}
			else {
				thos.nextPage();
			}
		});
		
		return canvas;
	}

	print() {
		let thos = this;
		this.pages.forEach((page, index) => {
			thos.pdf.addImage(page.toDataURL("image/jpeg"), 'JPEG', 0, 0, 21, 29.7);
			if(index < thos.pages.length - 1) thos.pdf.addPage();
		});
		
		this.save();
	};
	
	save() {
		this.pdf.save();
	}
};

var PDF = new pdf();