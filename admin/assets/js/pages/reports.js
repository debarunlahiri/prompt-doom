import { api } from "../core/api.js";
import {
  $,
  $$,
  empty,
  escapeHtml,
  formatDate,
  hero,
  refreshIcons,
  toast,
} from "../components/ui.js";

export const reportsPage = {
  id: "reports",
  label: "Reports",
  icon: "flag",
  kicker: "MODERATION",
  async render() {
    const data = await api("/admin/reports?limit=100");
    const content = data.items.length
      ? `<div class="report-list">${data.items.map((report) => `<article class="report-card"><div class="report-top"><div><span class="badge ${report.status}">${report.status}</span><h3>${escapeHtml(report.image?.title)}</h3></div><small>${formatDate(report.createdAt)}</small></div><p class="reason">${escapeHtml(report.reason)}</p>${report.details ? `<p>${escapeHtml(report.details)}</p>` : ""}<small>Reported by ${escapeHtml(report.user?.email)}</small><div class="report-actions"><button class="button" data-report="${report.id}" data-status="reviewed">Reviewed</button><button class="button" data-report="${report.id}" data-status="dismissed">Dismiss</button><button class="button danger" data-report="${report.id}" data-status="actioned">Action report</button></div></article>`).join("")}</div>`
      : empty("No reports", "There are no content reports to review.");
    $("#content").innerHTML =
      hero(
        "Content reports",
        "Review flagged artwork and record moderation decisions.",
      ) + `<div class="panel">${content}</div>`;
    $$("[data-report]").forEach((button) => {
      button.onclick = async () => {
        await api(`/admin/reports/${button.dataset.report}`, {
          method: "PATCH",
          body: JSON.stringify({ status: button.dataset.status }),
        });
        toast("Report updated.");
        await this.render();
      };
    });
    refreshIcons();
  },
};
