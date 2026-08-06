import { api } from "../core/api.js";
import { $, eventBars, hero, icon, refreshIcons } from "../components/ui.js";

export const dashboardPage = {
  id: "dashboard",
  label: "Overview",
  icon: "layout-dashboard",
  kicker: "COMMAND CENTER",
  async render() {
    const data = await api("/admin/analytics?days=30");
    const cards = [
      ["Total users", data.summary.users, "users", "Community accounts"],
      [
        "Published images",
        data.summary.publishedImages,
        "file-image",
        "Live in the gallery",
      ],
      [
        "Pending reports",
        data.summary.pendingReports,
        "flag",
        "Require review",
      ],
      ["Favorites", data.summary.favorites, "sparkles", "Saved relationships"],
    ];
    $("#content").innerHTML =
      hero(
        "Good to see you.",
        "Here is the latest activity across Prompt Doom.",
      ) +
      `<div class="stats">${cards.map(([label, value, cardIcon, note]) => `<article class="stat"><div class="stat-icon">${icon(cardIcon)}</div><p>${label}</p><strong>${Number(value).toLocaleString()}</strong><small>${note}</small></article>`).join("")}</div><div class="panel"><div class="panel-head"><div><h2>Audience activity</h2><p>Events recorded in the last 30 days</p></div></div>${eventBars(data.events)}</div>`;
    refreshIcons();
  },
};
