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
          //alert("Please enter a valid email address.");
          return false; // Prevent form submission
        }

        var mode = document.querySelector('[id="wpocf_cf_auth_mode-select"]');

        if (mode && mode.value === "0") {
          var apiKey = document.querySelector('[id="wpocf_cf_apikey"]');
          if (apiKey.value.trim() === "") {
            // alert("Please fill all required fields.");
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
            //alert("Please fill all required fields.");
            return false;
          }
        }

        document.body.appendChild(form);
        setTimeout(function () {
          form.submit();
        }, 2000);
      });
  });

  document.addEventListener("click", function (e) {
    // Redis elements
    const redisEnable = document.querySelector(
      "#wpoven-triple-cache-redis_enable .cb-enable"
    );
    const redisDisable = document.querySelector(
      "#wpoven-triple-cache-redis_enable .cb-disable"
    );
    const redisInput = document.querySelector(
      "input[name='wpoven-triple-cache[redis_enable]']"
    );

    // File elements
    const fileEnable = document.querySelector(
      "#wpoven-triple-cache-file_enable .cb-enable"
    );
    const fileDisable = document.querySelector(
      "#wpoven-triple-cache-file_enable .cb-disable"
    );
    const fileInput = document.querySelector(
      "input[name='wpoven-triple-cache[file_enable]']"
    );

    if (!redisEnable || !fileEnable) return;

    // If Redis is Enabled → Disable File Cache
    if (
      e.target.closest(".cb-enable") &&
      e.target.closest("#wpoven-triple-cache-redis_enable")
    ) {
      // Set Redis ON
      redisInput.value = "1";
      redisEnable.classList.add("selected");
      redisDisable.classList.remove("selected");

      // Set File OFF
      fileInput.value = "0";
      fileEnable.classList.remove("selected");
      fileDisable.classList.add("selected");
    }

    // If File Cache is Enabled → Disable Redis
    if (
      e.target.closest(".cb-enable") &&
      e.target.closest("#wpoven-triple-cache-file_enable")
    ) {
      // Set File ON
      fileInput.value = "1";
      fileEnable.classList.add("selected");
      fileDisable.classList.remove("selected");

      // Set Redis OFF
      redisInput.value = "0";
      redisEnable.classList.remove("selected");
      redisDisable.classList.add("selected");
    }
  });

  async function flush_cache() {
    const ajax_nonce = document.getElementById("wpoven-ajax-nonce").innerText;
    const ajax_url = document.getElementById("wpoven-ajax-url").innerText;

    try {
      const response = await fetch(ajax_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=utf-8",
        },
        body: `action=wpoven_flush_object_cache&security=${ajax_nonce}`,
        credentials: "same-origin",
      });
      const data = await response.json();
      if (data.status === "success") {
        alert(data.message);
      } else {
        alert(data.message);
      }
    } catch (error) {
      console.error("Error flushing cache:", error);
    }
  }

  async function flush_varnish_cache() {
    const ajax_nonce = document.getElementById("wpoven-ajax-nonce").innerText;
    const ajax_url = document.getElementById("wpoven-ajax-url").innerText;

    try {
      const response = await fetch(ajax_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=utf-8",
        },
        body: `action=wpoven_flush_varnish_cache&security=${ajax_nonce}`,
        credentials: "same-origin",
      });
      const data = await response.json();
      if (data.status === "success") {
        alert(data.message);
      } else {
        alert(data.message);
      }
    } catch (error) {
      console.error("Error flushing varnish cache:", error);
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.addEventListener("click", function (e) {
      if (e.target && e.target.id === "wpocf_redis_flush") {
        flush_cache();
      }
      if (e.target && e.target.id === "varnish_cache_flush") {
        flush_varnish_cache();
      }
    });
  });
})(jQuery);
