(function () {
  document.querySelectorAll("[data-confirm]").forEach(function (form) {
    form.addEventListener("submit", function (event) {
      if (!window.confirm(form.getAttribute("data-confirm"))) event.preventDefault();
    });
  });

  var menuButton = document.querySelector(".portal-menu-toggle");
  var navigation = document.querySelector(".sidebar-nav");
  if (menuButton && navigation) {
    menuButton.addEventListener("click", function () {
      var open = navigation.classList.toggle("mobile-open");
      menuButton.classList.toggle("open", open);
      menuButton.setAttribute("aria-expanded", String(open));
    });
    navigation.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        navigation.classList.remove("mobile-open");
        menuButton.classList.remove("open");
        menuButton.setAttribute("aria-expanded", "false");
      });
    });
  }

  document.querySelectorAll(".password-form").forEach(function (form) {
    var password = form.querySelector('[name="password"], [name="new_password"]');
    var confirmation = form.querySelector('[name="password_confirmation"], [name="confirm_password"]');
    var match = form.querySelector(".password-match");
    if (!password) return;

    function updatePasswordStatus() {
      var value = password.value;
      var checks = {
        length: value.length >= 10,
        uppercase: /[A-Z]/.test(value),
        lowercase: /[a-z]/.test(value),
        number: /\d/.test(value),
        symbol: /[^A-Za-z0-9]/.test(value)
      };
      Object.keys(checks).forEach(function (key) {
        var item = form.querySelector('[data-requirement="' + key + '"], [data-rule="' + key + '"]');
        if (item) item.classList.toggle("met", checks[key]);
      });
      if (confirmation && match) {
        match.textContent = confirmation.value
          ? (confirmation.value === value ? "Las contraseñas coinciden." : "Las contraseñas no coinciden.")
          : "";
        match.classList.toggle("met", Boolean(confirmation.value && confirmation.value === value));
      }
    }

    password.addEventListener("input", updatePasswordStatus);
    if (confirmation) confirmation.addEventListener("input", updatePasswordStatus);
    updatePasswordStatus();
  });

  document.querySelectorAll(".member-photo-with-fallback").forEach(function (image) {
    function showFallback() {
      if (!image.parentNode) return;
      var fallback = document.createElement("div");
      fallback.className = "member-initial" + (image.dataset.fallbackLarge ? " large" : "");
      fallback.textContent = image.dataset.fallbackInitial || "S";
      image.replaceWith(fallback);
    }
    image.addEventListener("error", showFallback, { once: true });
    if (image.complete && image.naturalWidth === 0) showFallback();
  });

  var memberGrid = document.querySelector("#member-directory-grid");
  var memberSearch = document.querySelector("#member-directory-search");
  var memberSort = document.querySelector("#member-sort");
  var memberCountry = document.querySelector("#member-country-filter");
  var memberRoleFilters = Array.from(document.querySelectorAll("[data-member-role-filter]"));
  var memberCount = document.querySelector("#member-directory-count");
  var memberEmpty = document.querySelector("#member-directory-empty");
  if (memberGrid && memberSearch && memberSort && memberCount) {
    var memberCards = Array.from(memberGrid.querySelectorAll(".private-member-card"));
    var activeRole = "all";
    var normalizeText = function (value) {
      return (value || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLocaleLowerCase("es");
    };
    var compareText = function (a, b) {
      return a.localeCompare(b, "es", { sensitivity: "base", numeric: true });
    };
    var renderMembers = function () {
      var term = normalizeText(memberSearch.value.trim());
      var sort = memberSort.value;
      var country = memberCountry ? normalizeText(memberCountry.value) : "";
      var visible = memberCards.filter(function (card) {
        var matchesRole = activeRole === "all"
          || (activeRole === "founder" && card.dataset.memberFounder === "1")
          || (activeRole === "board" && card.dataset.memberBoard === "1");
        return normalizeText(card.dataset.memberName).includes(term)
          && (!country || normalizeText(card.dataset.memberCountry) === country)
          && matchesRole;
      });
      visible.sort(function (a, b) {
        if (sort === "tenure") {
          return a.dataset.memberJoined.localeCompare(b.dataset.memberJoined)
            || compareText(a.dataset.memberName, b.dataset.memberName);
        }
        return compareText(
          sort === "surname" ? a.dataset.memberSurname : a.dataset.memberName,
          sort === "surname" ? b.dataset.memberSurname : b.dataset.memberName
        ) || compareText(a.dataset.memberName, b.dataset.memberName);
      });
      memberCards.forEach(function (card) { card.hidden = true; });
      visible.forEach(function (card) {
        card.hidden = false;
        memberGrid.appendChild(card);
      });
      memberCount.innerHTML = "<strong>" + visible.length + (visible.length === 1 ? " Peñista" : " Peñistas") + "</strong><span>"
        + (term || country || activeRole !== "all" ? "Resultado de los filtros activos." : "Mostrando toda La Peña.") + "</span>";
      if (memberEmpty) memberEmpty.hidden = visible.length !== 0;
    };
    memberSearch.addEventListener("input", renderMembers);
    memberSort.addEventListener("change", renderMembers);
    if (memberCountry) memberCountry.addEventListener("change", renderMembers);
    memberRoleFilters.forEach(function (button) {
      button.addEventListener("click", function () {
        activeRole = button.dataset.memberRoleFilter || "all";
        memberRoleFilters.forEach(function (item) {
          var active = item === button;
          item.classList.toggle("active", active);
          item.setAttribute("aria-pressed", String(active));
        });
        renderMembers();
      });
    });
    renderMembers();
  }
})();
