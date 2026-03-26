// Get the modal
var modal = document.getElementById("formModal");

// Get the button that opens the modal
var editBtn = document.getElementById("editBtn");
var newBtn = document.getElementById("newBtn");

// Get the <span> element that closes the modal
var span = document.getElementsByClassName("close")[0];

// When the user clicks on the button, open the modal
editBtn.onclick = function() {
  form.style.display = "block";
}

newBtn.onclick = function() {
  form.style.display = "block";
}

// When the user clicks on <span> (x), close the modal
span.onclick = function() {
  form.style.display = "none";
}

// When the user clicks anywhere outside of the modal, close it
window.onclick = function(event) {
  if (event.target == form) {
    form.style.display = "none";
  }
}