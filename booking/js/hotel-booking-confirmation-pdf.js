/**
 * Save payment confirmation card as a PDF file (html2canvas + jsPDF).
 * Why: Screenshot PDF loses HTML anchors — map Manage my booking hit-box to a PDF /URI link.
 */
(function () {
  'use strict';

  function hbPdfAbsUrl(href) {
    var raw = String(href || '').trim();
    if (raw === '') {
      return '';
    }
    try {
      return new URL(raw, window.location.href).href;
    } catch (err) {
      return raw;
    }
  }

  /**
   * Measure Manage my booking anchor inside the capture root and add a jsPDF link annotation.
   */
  function hbPdfAddManageBookingLink(pdf, root, pdfWidth, pdfHeight, imgHeight) {
    if (!pdf || !root || typeof pdf.link !== 'function') {
      return false;
    }
    var linkEl = root.querySelector('[data-hb-pdf-manage-link="1"]');
    if (!linkEl) {
      return false;
    }
    var href = hbPdfAbsUrl(linkEl.getAttribute('href') || linkEl.href || root.getAttribute('data-hb-manage-url') || '');
    if (href === '' || !/^https?:\/\//i.test(href)) {
      return false;
    }

    var rootRect = root.getBoundingClientRect();
    var linkRect = linkEl.getBoundingClientRect();
    if (!rootRect.width || !rootRect.height || !linkRect.width || !linkRect.height) {
      return false;
    }

    // Why: html2canvas output maps 1:1 onto the full-height image; convert DOM box → PDF mm.
    var x = ((linkRect.left - rootRect.left) / rootRect.width) * pdfWidth;
    var yInImage = ((linkRect.top - rootRect.top) / rootRect.height) * imgHeight;
    var w = (linkRect.width / rootRect.width) * pdfWidth;
    var h = (linkRect.height / rootRect.height) * imgHeight;
    // Slightly enlarge hit target for finger/mouse accuracy on the rasterized label.
    var padX = Math.min(2, w * 0.15);
    var padY = Math.min(1.5, h * 0.35);
    x = Math.max(0, x - padX);
    w = Math.min(pdfWidth - x, w + padX * 2);
    yInImage = Math.max(0, yInImage - padY);
    h = h + padY * 2;

    if (w < 1 || h < 0.5 || imgHeight < 1) {
      return false;
    }

    var pageCount = Math.max(1, Math.ceil(imgHeight / pdfHeight));
    var pageIndex = Math.min(pageCount - 1, Math.floor(yInImage / pdfHeight));
    var yOnPage = yInImage - pageIndex * pdfHeight;
    // Clamp height to remaining page so the annotation stays on one page.
    var maxH = pdfHeight - yOnPage;
    if (maxH < 0.5) {
      return false;
    }
    h = Math.min(h, maxH);

    if (typeof pdf.setPage === 'function') {
      pdf.setPage(pageIndex + 1);
    }
    pdf.link(x, yOnPage, w, h, { url: href });
    return true;
  }

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

      hbPdfAddManageBookingLink(pdf, root, pdfWidth, pdfHeight, imgHeight);

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
