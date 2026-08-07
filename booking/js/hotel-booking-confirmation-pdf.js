/**
 * Save payment confirmation card as a PDF file (html2canvas + jsPDF).
 */
(function () {
  'use strict';

  async function saveBookingConfirmationPdf(root, options) {
    options = options || {};
    if (!root) {
      return false;
    }
    if (typeof html2canvas === 'undefined' || !window.jspdf || !window.jspdf.jsPDF) {
      window.alert('PDF download is unavailable because the PDF library did not load. Refresh the page and try again.');
      return false;
    }

    var filename = (root.getAttribute('data-pdf-filename') || 'booking-confirmation.pdf').trim();
    if (!/\.pdf$/i.test(filename)) {
      filename += '.pdf';
    }

    var exclude = root.querySelectorAll('.hb-pdf-exclude');
    var hidden = [];
    exclude.forEach(function (el) {
      hidden.push({ el: el, display: el.style.display });
      el.style.display = 'none';
    });

    try {
      var canvas = await html2canvas(root, {
        scale: 2,
        backgroundColor: '#ffffff',
        useCORS: true,
        logging: false,
      });
      var pdf = new window.jspdf.jsPDF('p', 'mm', 'a4');
      var pdfWidth = pdf.internal.pageSize.getWidth();
      var pdfHeight = pdf.internal.pageSize.getHeight();
      var imgHeight = (canvas.height * pdfWidth) / canvas.width;
      var heightLeft = imgHeight;
      var position = 0;
      var imageData = canvas.toDataURL('image/png');

      pdf.addImage(imageData, 'PNG', 0, position, pdfWidth, imgHeight);
      heightLeft -= pdfHeight;
      while (heightLeft > 0) {
        position = heightLeft - imgHeight;
        pdf.addPage();
        pdf.addImage(imageData, 'PNG', 0, position, pdfWidth, imgHeight);
        heightLeft -= pdfHeight;
      }

      if (typeof window.showSaveFilePicker === 'function') {
        try {
          var handle = await window.showSaveFilePicker({
            suggestedName: filename,
            types: [{
              description: 'PDF Document',
              accept: {
                'application/pdf': ['.pdf']
              }
            }]
          });
          var writable = await handle.createWritable();
          var pdfBlob = pdf.output('blob');
          await writable.write(pdfBlob);
          await writable.close();
        } catch (pickerErr) {
          if (pickerErr.name !== 'AbortError') {
            pdf.save(filename);
          } else {
            return false;
          }
        }
      } else {
        pdf.save(filename);
      }
      return true;
    } catch (err) {
      window.alert('Could not create the PDF. Please try again.');
      return false;
    } finally {
      hidden.forEach(function (item) {
        item.el.style.display = item.display;
      });
    }
  }

  window.hbSaveBookingConfirmationPdf = saveBookingConfirmationPdf;

  function bindSaveButton() {
    var root = document.getElementById('hb-payment-confirmation-pdf-root');
    var btn = document.getElementById('hb-save-confirmation-pdf');
    if (!root || !btn || btn.dataset.hbPdfBound === '1') {
      return;
    }
    btn.dataset.hbPdfBound = '1';
    btn.addEventListener('click', function () {
      btn.disabled = true;
      saveBookingConfirmationPdf(root).finally(function () {
        btn.disabled = false;
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    bindSaveButton();
    if (document.body && document.body.getAttribute('data-hb-auto-pdf') === '1') {
      var root = document.getElementById('hb-payment-confirmation-pdf-root');
      if (root) {
        window.setTimeout(function () {
          saveBookingConfirmationPdf(root);
        }, 400);
      }
    }
  });
})();
