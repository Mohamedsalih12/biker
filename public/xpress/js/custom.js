$(document).ready(function () {

    alertify.set('notifier','position', 'top-right');
    // Ensure jQuery AJAX sends CSRF token when meta is present
    try {
        var csrfToken = $('meta[name="csrf-token"]').attr('content');
        if (csrfToken) {
            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': csrfToken } });
        }
    } catch (e) {}
});

function printMyBillingArea() {
    var el = document.getElementById("myBillingArea");
    if (!el) { console.warn('printMyBillingArea: #myBillingArea not found'); return; }
    var divContents = el.innerHTML;
    var win = window.open('', '');
    win.document.write('<html><head><title>POS System in PHP</title>');
    win.document.write(`
  <style>
/* Invoice Viewing with table */
    @page {
        size: A4;
        margin: 10mm;
    }
    @media print {
        body {
            margin: 0;
        }
        .invoice-wrapper {
            page-break-inside: avoid;
        }
        .header-images {
            page-break-inside: avoid;
        }
    }
    .a4-container {
        width: 100%;
        height: 100%;
        box-sizing: border-box;
    }
    body {
        font-family: "Segoe UI", Tahoma, Arial, sans-serif;
        background: #f4f4f4;
        margin: 0;
        padding: 20px;
    }
    .invoice-wrapper {
        max-width: 900px;
        margin: auto;
        background: #fff;
        padding: 30px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    /* Header Images */
    .header-images {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        gap: 10px;
        max-height: 150px;
    }
    .header-images > div {
        flex: 1;
        max-height: 150px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .img-box {
        width: 100%;
        max-height: 150px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .img-box a {
        display: flex;
        align-items: center;
        justify-content: center;
        max-height: 150px;
    }
    .img-box img, .img-thumb {
        max-width: 100%;
        max-height: 150px;
        width: auto;
        height: auto;
        object-fit: contain;
        display: block;
    }
    .placeholder-img {
        width: 100px;
        height: 100px;
        border: 2px dashed #ccc;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: #999;
    }
    .image-row {
         display: flex;
         justify-content: space-between;
         margin-top: 10mm;
         gap: 10px;
    }
    .image-box {
        width: 100%;
        max-height: 80mm;
        border: 1px solid #000;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .image-box img {
        max-width: 100%;
        max-height: 80mm;
        width: auto;
        height: auto;
        object-fit: contain;
    }
    .title {
        text-align: center;
        font-size: 26px;
        font-weight: bold;
        margin: 20px 0;
    }
    /* Company & Invoice Info */
    .info-section {
        display: flex;
        justify-content: space-between;
        margin-bottom: 25px;
    }
    .info-box {
        width: 48%;
        font-size: 14px;
    }
    .info-box h4 {
        margin-bottom: 8px;
        font-size: 15px;
        border-bottom: 1px solid #ddd;
        padding-bottom: 5px;
    }
    /* Table */
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        font-size: 14px;
    }
    table thead {
        background: #f0f0f0;
    }
    table th, table td {
        border: 1px solid #ddd;
        padding: 10px;
        text-align: left;
    }
    table th {
        text-align: center;
    }
    tbody tr:nth-child(odd)  { background-color: #fff; }
    tbody tr:nth-child(even) { background-color: #f9f9f9; }
    .text-right {
        text-align: right;
    }
    /* Totals */
    .totals {
        width: 40%;
        margin-left: auto;
        margin-top: 20px;
        font-size: 14px;
    }
    .totals table {
        border: none;
    }
    .totals td {
        border: none;
        padding: 6px 8px;
    }
    .totals tr:last-child td {
        font-weight: bold;
        border-top: 1px solid #000;
    }
    /* Footer */
    .footer {
        margin-top: 40px;
        display: flex;
        justify-content: space-between;
        font-size: 14px;
    }
    .signature {
        text-align: center;
        margin-top: 50px;
    }
    .signature span {
        display: block;
        border-top: 1px solid #000;
        width: 200px;
        margin: auto;
        padding-top: 5px;
    }
      </style>
    `);
    win.document.write('</head><body>');
    win.document.write(divContents);
    win.document.write('</body></html>');
    win.document.close();
    win.focus();
    try { win.print(); } catch (e) { console.warn('printMyBillingArea: print failed', e); }
    // win.close(); // optionally close after print
}

// Lazy-initialize jsPDF when needed (library may load after this script)
var docPDF = null;
function ensureJsPDF() {
    if (docPDF) return docPDF;

    try {
        if (window.jspdf && window.jspdf.jsPDF) {
            docPDF = new window.jspdf.jsPDF();
            return docPDF;
        }

        if (window.jsPDF) {
            docPDF = new window.jsPDF();
            return docPDF;
        }
    } catch (e) {
        console.warn('Failed to instantiate jsPDF', e);
        docPDF = null;
    }

    console.warn('jsPDF is not available on this page. PDF download will be disabled.');
    return null;
}

function downloadPDF(invoiceNo) {
    var pdf = ensureJsPDF();
    if (!pdf) {
        alert('PDF generation is not available because jsPDF is not loaded.');
        return;
    }

    var elementHTML = document.querySelector('#myBillingArea');
    if (!elementHTML) {
        alert('Printable area not found');
        return;
    }

    pdf.html(elementHTML, {
        callback: function() {
            try { pdf.save(invoiceNo + '.pdf'); } catch (e) { console.warn('Failed to save PDF', e); }
        },
        x: 10,
        y: 10,
        width: 170,
        windowWidth: 650
    });
}