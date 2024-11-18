function displayList() {
  document.getElementById("dropdown").classList.toggle("show");
}

function updateField() {
  var number = parseFloat(document.getElementById('total').value) || 0;
  var result = number / 4; // Divide by 4 for four people
  result = Math.round(result * 100) / 100;
  document.getElementById('cost').value = result.toFixed(2);
}

// Close the dropdown if the user clicks outside of it
window.onclick = function(event) {
if (!event.target.matches('.dropbtn')) {

  var dropdowns = document.getElementsByClassName("dropdown-content");
  var i;
  for (i = 0; i < dropdowns.length; i++) {
    var openDropdown = dropdowns[i];
    if (openDropdown.classList.contains('show')) {
      openDropdown.classList.remove('show');
    }
  }
}
}