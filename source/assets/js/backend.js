let wpocf_toolbar_cache_status_tries = 0;
let wpocf_toolbar_cache_status_interval = null;

document.addEventListener("DOMContentLoaded", function (event) {
  var element = document.querySelector('[id="wpocf_cf_zoneid-select"]');
  if (element !== null && element.value) {
    var element = document.querySelector(
      '[id="wpocf_submit_enable_page_cache"]'
    );
    if (element !== null) {
      element.classList.remove("wpocf_hide");
    }
  }
});

document.addEventListener("DOMContentLoaded", function (event) {
  var element = document.querySelector('[id="wpocf_cf_apitoken_domain"]');
  if (element !== null && element.value) {
    var element = document.querySelector(
      '[id="wpocf_submit_enable_page_cache"]'
    );
    if (element !== null) {
      element.classList.remove("wpocf_hide");
    }
  }
});

function waitDiv() {
  const waitDiv = document.createElement("div");
  waitDiv.classList.add("wpocf_please_wait");
  document.body.prepend(waitDiv);
}

function wpocf_refresh_page() {
  window.location.reload();
}

async function wpocf_test_page_cache() {
  try {
    const ajax_nonce = document.getElementById("wpocf-ajax-nonce").innerText;

    waitDiv();

    const response = await fetch(wpocf_ajax_url, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=utf-8",
      },
      body: `action=wpocf_test_page_cache&security=${ajax_nonce}`,
      credentials: "same-origin",
      timeout: 10000,
    });

    document.querySelector(".wpocf_please_wait").remove();

    if (response.ok) {
      const data = await response.json();

      if (data.status === "ok") {
        alert(data.success_msg);
        wpocf_refresh_page();
      } else {
        alert(data.error);
      }
    } else {
      console.error(`Error: ${response.status} ${response.statusText}`);
    }
  } catch (err) {
    alert(`Error: ${err.status} ${err.message}`);
    console.error(err);
  }
}

async function wpocf_enable_page_cache() {
  try {
    const ajax_nonce = document.getElementById("wpocf-ajax-nonce").innerText;

    waitDiv();

    const response = await fetch(wpocf_ajax_url, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=utf-8",
      },
      body: `action=wpocf_enable_page_cache&security=${ajax_nonce}`,
      credentials: "same-origin",
      timeout: 10000,
    });

    document.querySelector(".wpocf_please_wait").remove();

    if (response.ok) {
      const data = await response.json();
      if (data.status === "ok") {
        alert(data.success_msg);
        wpocf_refresh_page();
      } else {
        alert(data.error);
      }
    } else {
      console.error(`Error: ${response.status} ${response.statusText}`);
    }
  } catch (err) {
    alert(`Error: ${err.status} ${err.message}`);
    // console.error(err);
  }
}

async function wpocf_disable_page_cache() {
  try {
    const ajax_nonce = document.getElementById("wpocf-ajax-nonce").innerText;

    waitDiv();

    const response = await fetch(wpocf_ajax_url, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=utf-8",
      },
      body: `action=wpocf_disable_page_cache&security=${ajax_nonce}`,
      credentials: "same-origin",
      timeout: 10000,
    });

    document.querySelector(".wpocf_please_wait").remove();

    if (response.ok) {
      const data = await response.json();
      if (data.status === "ok") {
        alert(data.success_msg);
        wpocf_refresh_page();
      } else {
        alert(data.error);
      }
    } else {
      console.error(`Error: ${response.status} ${response.statusText}`);
    }
  } catch (err) {
    alert(`Error: ${err.status} ${err.message}`);
    console.error(err);
  }
}

async function wpocf_reset_all() {
  try {
    const ajax_nonce = document.getElementById("wpocf-ajax-nonce").innerText;

    waitDiv();

    const response = await fetch(wpocf_ajax_url, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=utf-8",
      },
      body: `action=wpocf_reset_all&security=${ajax_nonce}`,
      credentials: "same-origin",
      timeout: 10000,
    });

    document.querySelector(".wpocf_please_wait").remove();

    if (response.ok) {
      const data = await response.json();
      if (data.status === "ok") {
        alert(data.success_msg);
        wpocf_refresh_page();
      } else {
        alert(data.error);
      }
    } else {
      console.error(`Error: ${response.status} ${response.statusText}`);
    }
  } catch (err) {
    alert(`Error: ${err.status} ${err.message}`);
    console.error(err);
  }
}

async function wpocf_purge_whole_cache() {
  try {
    const ajax_nonce = document.getElementById("wpocf-ajax-nonce").innerText;

    waitDiv();

    const response = await fetch(wpocf_ajax_url, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=utf-8",
      },
      body: `action=wpocf_purge_whole_cache&security=${ajax_nonce}`,
      credentials: "same-origin",
      timeout: 10000,
    });

    document.querySelector(".wpocf_please_wait").remove();

    if (response.ok) {
      const data = await response.json();
      if (data.status === "ok") {
        alert(data.success_msg);
      } else {
        alert(data.error);
      }
    } else {
      console.error(`Error: ${response.status} ${response.statusText}`);
    }
  } catch (err) {
    alert(`Error: ${err.status} ${err.message}`);
    console.error(err);
  }
}

async function wpocf_purge_single_post_cache(post_id) {
  try {
    const ajax_nonce = document.getElementById("wpocf-ajax-nonce").innerText;
    const dataJSON = encodeURIComponent(
      JSON.stringify({
        post_id: post_id,
      })
    );

    waitDiv();

    const response = await fetch(wpocf_ajax_url, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=utf-8",
      },
      body: `action=wpocf_purge_single_post_cache&security=${ajax_nonce}&data=${dataJSON}`,
      credentials: "same-origin",
      timeout: 10000,
    });

    document.querySelector(".wpocf_please_wait").remove();

    if (response.ok) {
      const data = await response.json();

      if (data.status === "ok") {
        alert(data.success_msg);
      } else {
        alert(data.error);
      }
    } else {
      console.error(`Error: ${response.status} ${response.statusText}`);
    }
  } catch (err) {
    alert(`Error: ${err.status} ${err.message}`);
    console.error(err);
  }
}

function wpocf_update_toolbar_cache_status() {
  if (
    document.getElementById("wp-admin-bar-wpocf-cache-toolbar-container") !=
    null
  ) {
    if (wpocf_cache_enabled == 0) {
      document
        .getElementById("wp-admin-bar-wpocf-cache-toolbar-container")
        .classList.remove("bullet-green");
      document
        .getElementById("wp-admin-bar-wpocf-cache-toolbar-container")
        .classList.add("bullet-red");
    } else {
      document
        .getElementById("wp-admin-bar-wpocf-cache-toolbar-container")
        .classList.remove("bullet-red");
      document
        .getElementById("wp-admin-bar-wpocf-cache-toolbar-container")
        .classList.add("bullet-green");
    }

    clearInterval(wpocf_toolbar_cache_status_interval);
  } else {
    wpocf_toolbar_cache_status_tries++;
  }
}

document.addEventListener("DOMContentLoaded", function (event) {
  var buttons = document.querySelectorAll(".button-primary");

  buttons.forEach(function (button) {
    button.addEventListener("click", function (event) {
      event.preventDefault();
      var buttonId = this.getAttribute("id");
      if (buttonId === "wpocf_submit_enable_page_cache") {
        wpocf_enable_page_cache();
      }
      if (buttonId === "wpocf_submit_disable_page_cache") {
        wpocf_disable_page_cache();
      }
      if (buttonId === "wpocf_submit_purge_cache") {
        wpocf_purge_whole_cache();
      }
      if (buttonId === "wpocf_submit_test_cache") {
        wpocf_test_page_cache();
      }
      if (buttonId === "wpocf_submit_reset_all") {
        if (confirm("Are you sure you want reset all?")) {
          wpocf_reset_all();
        }
      }
    });
  });

  if (document.querySelector(".wpocf_action_row_single_post_cache_purge")) {
    document
      .querySelectorAll(".wpocf_action_row_single_post_cache_purge")
      .forEach((item) => {
        item.addEventListener("click", (e) => {
          e.preventDefault();
          const post_id = e.target.dataset.post_id;
          wpocf_purge_single_post_cache(post_id);
        });
      });
  }

  if (document.querySelector("#wp-admin-bar-wpocf-cache-toolbar-purge-all a")) {
    document
      .querySelector("#wp-admin-bar-wpocf-cache-toolbar-purge-all a")
      .addEventListener("click", (e) => {
        e.preventDefault();
        wpocf_purge_whole_cache();
      });
  }

  if (
    document.querySelector("#wp-admin-bar-wpocf-cache-toolbar-purge-single a")
  ) {
    document
      .querySelector("#wp-admin-bar-wpocf-cache-toolbar-purge-single a")
      .addEventListener("click", (e) => {
        e.preventDefault();
        const post_id = e.target.hash.replace("#", "");
        wpocf_purge_single_post_cache(post_id);
      });
  }
});

wpocf_toolbar_cache_status_interval = window.setInterval(
  wpocf_update_toolbar_cache_status,
  2000
);
