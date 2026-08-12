import { api } from "../core/api.js";
import {
  $,
  eventBars,
  hero,
  panel,
  panelHeader,
  refreshIcons,
} from "../components/ui.js?v=20260811-1";

export const analyticsPage = {
  id: "analytics",
  label: "Analytics",
  icon: "bar-chart-3",
  kicker: "INSIGHTS",
  async render() {
    const days = Number(sessionStorage.getItem("analytics-days") || 30);
    const data = await api(`/admin/analytics?days=${days}`);
    $("#content").innerHTML =
      hero(
        "Analytics",
        `Understand audience interactions over the last ${days} days.`,
      ) +
      panel(
        panelHeader(
          "Recorded events",
          "Grouped by interaction type",
          '<label class="analytics-period">Reporting period<select id="analytics-days" class="select-control"><option value="7">Last 7 days</option><option value="30">Last 30 days</option><option value="90">Last 90 days</option><option value="365">Last year</option></select></label>',
        ) + eventBars(data.events),
      );
    $("#analytics-days").value = String(days);
    $("#analytics-days").onchange = (event) => {
      sessionStorage.setItem("analytics-days", event.target.value);
      this.render();
    };
    refreshIcons();
  },
};
