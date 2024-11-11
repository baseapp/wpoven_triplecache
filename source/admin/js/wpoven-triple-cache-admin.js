(function ($) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    //remove extra menu title
    const menuItems = document.querySelectorAll("li#toplevel_page_wpoven");
    const menuArray = Array.from(menuItems);
    for (let i = 1; i < menuArray.length; i++) {
      menuArray[i].remove();
    }

    // Remove rudux munu title.
    var reduxMenu = document.querySelector(
      "li.toplevel_page_wpoven-triple-cache"
    );
    if (reduxMenu) {
      reduxMenu.remove();
    }
  });

  $(function () {
    // Function to validate email using regex
    function isValidEmail(email) {
      var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return emailRegex.test(email);
    }

    $('input[name="redux_save"]')
      .parent()
      .click(function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();

        var form = document.createElement("form");
        form.setAttribute("method", "post");

        var ids = [
          //   { id: "wpocf_index_nonce", type: "text" },
          { id: "wpocf_cf_auth_mode-select", type: "text" },
          { id: "wpocf_cf_email", type: "email" },
          { id: "wpocf_cf_apikey", type: "password" },
          { id: "wpocf_cf_zoneid-select", type: "text" },
          { id: "wpocf_cf_apitoken", type: "password" },
          { id: "wpocf_cf_apitoken_domain", type: "text" },
          { id: "wpocf_maxage", type: "text" },
          { id: "wpocf_browser_maxage", type: "text" },
          { id: "wpocf_cf_excluded_urls-textarea", type: "text" },
          { id: "wpocf_cf_strip_cookies", type: "text" },
          { id: "wpocf_cf_auto_purge_on_comments", type: "text" },
          {
            id: "wpocf_cf_auto_purge_on_upgrader_process_complete",
            type: "text",
          },
          { id: "wpocf_post_per_page", type: "text" },
          { id: "wpocf_cf_cache_control_htaccess", type: "text" },
          // { id: "wpocf_cf_bypass_backend_page_rule", type: "text" },
          { id: "wpocf_cf_purge_only_html", type: "text" },
          { id: "wpocf_cf_disable_cache_purging_queue", type: "text" },
        ];

        // Loop through the array and get the corresponding elements
        ids.forEach(function (item) {
          var element = document.querySelector('[id="' + item.id + '"]');
          if (element) {
            if (typeof element.value == "undefined") {
              element.value = null;
            }
            var newInput = document.createElement("input");
            newInput.setAttribute("type", item.type);
            newInput.setAttribute("name", item.id);
            newInput.setAttribute("value", element.value);
            form.appendChild(newInput);
          }
        });

        var doNotCache = document.querySelector('[id="do-not-cache-select"]');
        const doNotCacheOptions = Array.from(doNotCache.options);
        const doNotCacheOptionsValues = doNotCacheOptions.map(
          (option) => option.value
        );
        doNotCacheOptionsValues.forEach(function (item) {
          var newInput = document.createElement("input");
          newInput.setAttribute("type", "text");
          newInput.setAttribute("name", item);
          newInput.setAttribute("value", item.value);
          form.appendChild(newInput);
        });

        // Check if the email field is valid before submitting the form
        var emailField = document.querySelector('[id="wpocf_cf_email"]');
        if (emailField && !isValidEmail(emailField.value)) {
          alert("Please enter a valid email address.");
          return false; // Prevent form submission
        }

        var mode = document.querySelector('[id="wpocf_cf_auth_mode-select"]');

        if (mode && mode.value === "0") {
          var apiKey = document.querySelector('[id="wpocf_cf_apikey"]');
          if (apiKey.value.trim() === "") {
            alert("Please fill all required fields.");
            return false;
          }
        } else {
          var apiToken = document.querySelector('[id="wpocf_cf_apitoken"]');
          var apiTokenDomain = document.querySelector(
            '[id="wpocf_cf_apitoken_domain"]'
          );
          if (
            apiToken.value.trim() === "" ||
            apiTokenDomain.value.trim() === ""
          ) {
            alert("Please fill all required fields.");
            return false;
          }
        }

        document.body.appendChild(form);
        setTimeout(function () {
          form.submit();
        }, 2000);
      });
  });
})(jQuery);
