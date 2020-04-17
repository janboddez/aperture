//require('./bootstrap');
try {
  window.$ = window.jQuery = require('jquery');
} catch (e) {}

window.csrf_token = function() {
  return $('meta[name="csrf-token"]').attr('content');
}

window.reload_window = function() {
  window.location.reload(true);
}

document.addEventListener('DOMContentLoaded', function() {

  // Get all "navbar-burger" elements
  var $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);

  // Check if there are any navbar burgers
  if ($navbarBurgers.length > 0) {

    // Add a click event on each of them
    $navbarBurgers.forEach(function ($el) {
      $el.addEventListener('click', function () {

        // Get the target from the "data-target" attribute
        var target = $el.dataset.target;
        var $target = document.getElementById(target);

        // Toggle the class on both the "navbar-burger" and the "navbar-menu"
        $el.classList.toggle('is-active');
        $target.classList.toggle('is-active');

      });
    });
  }

  function closeActiveModal() {
    $(".modal.is-active").removeClass("is-active");
  }

  $(document).keyup(function(e){
    if(e.keyCode == 27) {
      closeActiveModal();
    }
  });

  $(".modal-background, .modal button.delete").click(function(e){
    closeActiveModal();
    e.preventDefault();
  });

  $(".hidden-secret").click(function(){
    $(this).addClass("reveal");
  });

  /* add http:// to URL fields on blur */
  /* add http:// to URL fields on blur or when enter is pressed */
  function addDefaultScheme(target) {
    if(target.value.match(/^(?!https?:).+\..+/)) {
      target.value = "http://"+target.value;
    }
  }
  var elements = document.querySelectorAll("input[type=url]");
  Array.prototype.forEach.call(elements, function(el, i){
    el.addEventListener("blur", function(e){
      addDefaultScheme(e.target);
    });
    el.addEventListener("keydown", function(e){
      if(e.keyCode == 13) {
        addDefaultScheme(e.target);
      }
    });
  });

});
