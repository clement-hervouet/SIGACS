document.addEventListener('DOMContentLoaded', () => {

// Container injected into the page to hold the loaded modal
var modalContainer = document.getElementById("modalContainer");

function openModal(file) {
  fetch(file)
    .then(function(response) { return response.text(); })
    .then(function(html) {
      modalContainer.innerHTML = html;
      modalContainer.style.display = "block";

      // Bind cancel button inside the freshly loaded modal
      var cancel = modalContainer.querySelector(".cancel");
      if (cancel) cancel.onclick = closeModal;
    });
}

function closeModal() {
  modalContainer.style.display = "none";
  modalContainer.innerHTML = "";
}

// Nav buttons
var newSerreBtn = document.getElementById("newSerreBtn");
var newBacBtn = document.getElementById("newBacBtn");
var newCultureBtn = document.getElementById("newCultureBtn");

if (newSerreBtn) newSerreBtn.onclick = function() { openModal("forms/new/new_serre.html");}
if (newBacBtn)   newBacBtn.onclick   = function() { openModal("forms/new/new_bac.html"); }
if (newCultureBtn) newCultureBtn.onclick = function() { openModal("forms/new/new_culture.html"); }

// Click outside the modal content to close
window.onclick = function(event) {
  if (event.target == modalContainer) {
    closeModal();
  }
}

});