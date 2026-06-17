function showDonorToast() { 
  const t = document.getElementById('d-toast'); 
  t.classList.add('show'); 
  setTimeout(() => t.classList.remove('show'), 3000); 
}
