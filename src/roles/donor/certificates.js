// certificates.js (Donor role)
//
// The certificate cards are now rendered server-side in certificates.php,
// pulling real rows from the `certificates` table for the logged-in donor.
// This file intentionally does NOT render any certificate data anymore —
// the old mock-data renderCertificates() has been removed because it was
// overwriting the real server-rendered cards with fake ones on every load.
//
// Nothing else on this page currently needs JS. If you later want small
// enhancements (e.g. a loading spinner on the download button, or a toast
// when a download starts), hook them in here.

document.addEventListener("DOMContentLoaded", () => {
  const downloadButtons = document.querySelectorAll(".btn-download:not([disabled])");

  downloadButtons.forEach((btn) => {
    btn.addEventListener("click", () => {
      // btn is an <a> with a real href from file_url, so the browser
      // handles the download natively — this is just a hook for optional
      // UI feedback (e.g. showing a brief "Downloading..." state).
    });
  });
});