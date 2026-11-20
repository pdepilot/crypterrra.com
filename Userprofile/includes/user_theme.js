/**
 * Shared user-area theme: body.light + localStorage key "theme".
 * Migrates legacy "crypterra-theme" once if "theme" is unset.
 */
(function () {
  function migrateLegacyKey() {
    try {
      var legacy = localStorage.getItem("crypterra-theme");
      if (!legacy) return;
      if (localStorage.getItem("theme") == null) {
        localStorage.setItem("theme", legacy === "light" ? "light" : "dark");
      }
      localStorage.removeItem("crypterra-theme");
    } catch (e) {}
  }

  function applyStoredTheme() {
    try {
      migrateLegacyKey();
      if (localStorage.getItem("theme") === "light") {
        document.body.classList.add("light");
      } else {
        document.body.classList.remove("light");
      }
    } catch (e) {}
  }

  function bindToggle() {
    var el = document.getElementById("theme-toggle");
    if (!el) return;
    el.addEventListener("click", function () {
      document.body.classList.toggle("light");
      try {
        localStorage.setItem(
          "theme",
          document.body.classList.contains("light") ? "light" : "dark",
        );
      } catch (e) {}
    });
  }

  function init() {
    applyStoredTheme();
    bindToggle();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
