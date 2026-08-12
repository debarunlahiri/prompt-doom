import { api } from "../core/api.js";
import {
  $,
  hero,
  icon,
  panel,
  panelHeader,
  refreshIcons,
  toast,
} from "../components/ui.js?v=20260811-1";

export const adsPage = {
  id: "ads",
  label: "Advertisement",
  icon: "megaphone",
  kicker: "MONETISATION",
  async render() {
    const data = await api("/ads/config");
    const config = data.config || {
      enabled: true,
      showAfterClicks: 5,
      minIntervalSeconds: 120,
      maxAdsPerSession: 3,
    };
    $("#content").innerHTML =
      hero(
        "Advertisement",
        "Control how advertisements appear in the mobile experience.",
      ) +
      panel(
        panelHeader(
          "Advertisement settings",
          "Control ad timing and session frequency.",
          `<span class="panel-icon">${icon("megaphone")}</span>`,
        ) +
          `<form id="ads-form"><label class="toggle-row"><div><strong>Enable advertisements</strong><p>Allow ads to appear during user sessions.</p></div><span class="switch-control"><input name="enabled" type="checkbox" ${config.enabled ? "checked" : ""}><span aria-hidden="true"></span></span></label><div class="form-grid settings-grid"><label>Show after clicks<input name="showAfterClicks" type="number" min="1" value="${config.showAfterClicks}" required></label><label>Minimum interval (seconds)<input name="minIntervalSeconds" type="number" min="0" value="${config.minIntervalSeconds}" required></label><label>Maximum ads per session<input name="maxAdsPerSession" type="number" min="0" value="${config.maxAdsPerSession}" required></label></div><div class="form-actions form-actions-start"><button class="primary">${icon("save")} Save settings</button></div></form>`,
        "settings",
      );
    $("#ads-form").onsubmit = async (event) => {
      event.preventDefault();
      const form = new FormData(event.target);
      await api("/admin/ad-settings", {
        method: "PUT",
        body: JSON.stringify({
          enabled: event.target.enabled.checked,
          showAfterClicks: Number(form.get("showAfterClicks")),
          minIntervalSeconds: Number(form.get("minIntervalSeconds")),
          maxAdsPerSession: Number(form.get("maxAdsPerSession")),
        }),
      });
      toast("Advertisement settings saved.");
    };
    refreshIcons();
  },
};
