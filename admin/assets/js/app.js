import { login, logout } from "./core/api.js";
import { saveSection, saveSession, state } from "./core/state.js";
import {
  $,
  $$,
  confirmDialog,
  loading,
  refreshIcons,
} from "./components/ui.js?v=20260811-1";
import { dashboardPage } from "./pages/dashboard.js?v=20260811-1";
import { usersPage } from "./pages/users.js?v=20260811-1";
import { imagesPage } from "./pages/images.js?v=20260811-1";
import { categoriesPage } from "./pages/categories.js?v=20260811-1";
import { tagsPage } from "./pages/tags.js?v=20260811-1";
import { adsPage } from "./pages/ads.js?v=20260811-1";
import { analyticsPage } from "./pages/analytics.js?v=20260811-1";
import { reportsPage } from "./pages/reports.js?v=20260811-1";
import { profilePage } from "./pages/profile.js?v=20260811-1";

if (window.location.search) {
  window.history.replaceState(null, "", window.location.pathname);
}

const pages = [
  dashboardPage,
  usersPage,
  imagesPage,
  categoriesPage,
  tagsPage,
  adsPage,
  analyticsPage,
  reportsPage,
  profilePage,
];
const pageMap = new Map(pages.map((page) => [page.id, page]));

function renderNavigation() {
  $("#navigation").innerHTML = pages
    .map(
      (page) =>
        `<button data-section="${page.id}" class="${state.section === page.id ? "active" : ""}"><i data-lucide="${page.icon}"></i><span>${page.label}</span></button>`,
    )
    .join("");
  $$("[data-section]").forEach((button) => {
    button.onclick = () => navigate(button.dataset.section);
  });
  refreshIcons();
}

async function navigate(section) {
  const page = pageMap.get(section) || dashboardPage;
  saveSection(page.id);
  renderNavigation();
  $("#section-title").textContent = page.label;
  $("#section-kicker").textContent = page.kicker;
  $("#sidebar").classList.remove("open");
  $("#content").innerHTML = loading(`Loading ${page.label.toLowerCase()}`);
  refreshIcons();
  try {
    await page.render();
  } catch (error) {
    $("#content").innerHTML =
      `<div class="alert"><strong>Unable to load ${page.label}</strong><br>${error.message}</div>`;
  }
}

function showApplication() {
  $("#login").classList.toggle("hidden", Boolean(state.session));
  $("#admin").classList.toggle("hidden", !state.session);
  if (state.session) {
    const account = state.session.account;
    $("#admin-name").textContent = account.name;
    $("#admin-email").textContent = account.email;
    $("#admin-initial").textContent = (account.name || "A")[0];
    navigate(state.section);
  }
  refreshIcons();
}

$("#login-form").onsubmit = async (event) => {
  event.preventDefault();
  const button = $("#login-form button");
  button.disabled = true;
  $("#login-error").classList.add("hidden");
  try {
    const form = new FormData(event.target);
    saveSession(await login(form.get("email"), form.get("password")));
    showApplication();
  } catch (error) {
    $("#login-error").textContent = error.message;
    $("#login-error").classList.remove("hidden");
  } finally {
    button.disabled = false;
  }
};

$("#logout").onclick = async () => {
  const confirmed = await confirmDialog({
    title: "Log out?",
    message: "Are you sure you want to log out?",
    confirmLabel: "Log out",
    tone: "danger",
    iconName: "log-out",
  });
  if (!confirmed) return;
  try {
    await logout();
  } finally {
    saveSession(null);
    showApplication();
  }
};
$("#menu").onclick = () => $("#sidebar").classList.toggle("open");
window.addEventListener("prompt-doom-session-expired", showApplication);
showApplication();
