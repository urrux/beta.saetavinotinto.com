const menuButton = document.querySelector(".menu-toggle");
const nav = document.querySelector("#main-nav");

if (menuButton && nav) {
  menuButton.addEventListener("click", () => {
    const open = nav.classList.toggle("open");
    menuButton.classList.toggle("open", open);
    menuButton.setAttribute("aria-expanded", String(open));
  });
  nav.querySelectorAll("a").forEach(link => link.addEventListener("click", () => {
    nav.classList.remove("open");
    menuButton.classList.remove("open");
    menuButton.setAttribute("aria-expanded", "false");
  }));
}

const coordinates = {
  "Venezuela":[27,58], "Argentina":[29,79], "Brazil":[34,69], "Brasil":[34,69],
  "Canada":[18,24], "Canadá":[18,24], "United States":[20,34], "Estados Unidos":[20,34],
  "Spain":[49,34], "España":[49,34], "Portugal":[47,36], "France":[51,32],
  "Germany":[54,29], "Alemania":[54,29], "United Kingdom":[49,26], "Reino Unido":[49,26],
  "Italy":[55,36], "Italia":[55,36], "Chile":[26,76], "Colombia":[29,57],
  "Mexico":[19,45], "México":[19,45], "Panama":[25,53], "Panamá":[25,53],
  "Ecuador":[27,61], "El Salvador":[20,50],
  "Australia":[87,76], "Netherlands":[52,27], "Países Bajos":[52,27]
};

let publicMembers = [];
const memberGrid = document.querySelector("#member-grid");
const memberSearch = document.querySelector("#member-search");
const memberCount = document.querySelector("#member-count");

function initials(name) { return name.split(" ").map(part => part[0]).join("").slice(0,2); }
function escapeHTML(value) {
  return value.replace(/[&<>"']/g, character => ({ "&":"&amp;", "<":"&lt;", ">":"&gt;", "\"":"&quot;", "'":"&#039;" }[character]));
}
function readRenderedMembers() {
  return [...memberGrid.querySelectorAll(".member-card")].map(card => ({
    name: card.querySelector("h3")?.textContent.trim() || "",
    badges: [...card.querySelectorAll(".member-recognitions span")].map(badge => badge.textContent.replace("★", "").trim())
  })).filter(member => member.name);
}
function renderPublicMembers() {
  const term = memberSearch.value.trim().toLowerCase();
  const visible = publicMembers.filter(member => member.name.toLowerCase().includes(term));
  memberGrid.innerHTML = visible.map(member => `<article class="member-card" data-member-name="${escapeHTML(member.name.toLowerCase())}"><div class="member-avatar">${escapeHTML(initials(member.name))}</div><div><h3>${escapeHTML(member.name)}</h3>${member.badges?.length ? `<div class="member-recognitions">${member.badges.map(badge => `<span class="${badge === "Fundador" ? "badge-founder" : "badge-board"}">${badge === "Fundador" ? "★ " : ""}${escapeHTML(badge)}</span>`).join("")}</div>` : ""}</div></article>`).join("");
  memberCount.textContent = `${visible.length} miembros`;
}
function renderMap(locations) {
  const coordinateFor = country => {
    if (coordinates[country]) return coordinates[country];
    const hash = [...country].reduce((total, character) => ((total * 31) + character.charCodeAt(0)) >>> 0, 7);
    return [12 + (hash % 76), 18 + ((hash >>> 8) % 64)];
  };
  document.querySelector("#map-markers").innerHTML = locations.map(location => {
    const [x,y]=coordinateFor(location.country); return `<span class="map-marker" style="left:${x}%;top:${y}%" title="${location.country}: ${location.count} miembro(s)"><span>${location.count}</span></span>`;
  }).join("");
  document.querySelector("#country-list").innerHTML = locations.sort((a,b)=>b.count-a.count).map(location=>`<article><strong>${location.country}</strong><span>${location.count} miembro${location.count===1?"":"s"}</span></article>`).join("");
}

publicMembers = readRenderedMembers();
fetch("portal/public-members.php").then(response => response.json()).then(members => { publicMembers=members; renderPublicMembers(); }).catch(()=>{ if (!publicMembers.length) memberCount.textContent="Directorio temporalmente no disponible"; });
fetch("portal/public-locations.php").then(response => response.json()).then(renderMap).catch(()=>{});
memberSearch.addEventListener("input", renderPublicMembers);

const accountLink = document.querySelector("#account-link");
const loginNotice = new URLSearchParams(window.location.search).get("logged");
if (accountLink && loginNotice === "1") {
  accountLink.classList.add("is-authenticated");
  accountLink.querySelector("strong").textContent = "Mi Espacio";
  accountLink.href = "portal/index.php";
  const notice = document.createElement("div");
  notice.className = "session-toast";
  notice.textContent = "Sesión iniciada. Mi espacio está activo.";
  document.body.appendChild(notice);
  window.setTimeout(() => notice.classList.add("show"), 50);
  window.setTimeout(() => notice.classList.remove("show"), 3600);
  window.history.replaceState({}, "", window.location.pathname + window.location.hash);
}

fetch("portal/session.php").then(response => response.json()).then(session => {
  if (session.authenticated && accountLink) {
    const label = accountLink?.querySelector("strong");
    accountLink.classList.add("is-authenticated");
    label.textContent = session.firstName ? `Mi Espacio` : "Mi Espacio";
    accountLink.href = "portal/index.php";
    accountLink.setAttribute("aria-label", session.firstName ? `Ir A Mi Espacio De ${session.firstName}` : "Ir A Mi Espacio");
  }
}).catch(()=>{});
