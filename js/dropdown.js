function displayList() {
    document.getElementById("dropdown").classList.toggle("show");
}

function updateField() {
  var number = document.getElementById('total').value;
  var result = number / 3;
  result = Math.round(result * 100) / 100;
  document.getElementById('cost').value = result;
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