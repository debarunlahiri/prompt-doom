import { state } from "../core/state.js";
import {
  $,
  escapeHtml,
  hero,
  icon,
  panel,
  panelHeader,
  refreshIcons,
} from "../components/ui.js?v=20260811-1";

export const profilePage = {
  id: "profile",
  label: "Profile",
  icon: "circle-user-round",
  kicker: "ACCOUNT",
  async render() {
    const account = state.session.account;
    $("#content").innerHTML =
      hero("Administrator profile", "Your current secured admin session.") +
      `<div class="profile-grid">${panel(`<div class="profile-avatar">${escapeHtml(account.name[0])}</div><div class="profile-details"><h2>${escapeHtml(account.name)}</h2><p>${escapeHtml(account.email)}</p><span class="role">${icon("shield-check")} Administrator</span></div>`, "profile-card")}${panel(panelHeader("Account security", "Security and access details for this administrator.", `<span class="panel-icon">${icon("user-round-cog")}</span>`) + `<div class="info-callout">${icon("shield-check")}<div><strong>Role-aware access is active</strong><p>Every management request is protected by an administrator access token with automatic refresh handling.</p></div></div>`)}</div>`;
    refreshIcons();
  },
};
