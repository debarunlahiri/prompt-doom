import { api, upload } from "../core/api.js";
import { loadTaxonomies } from "../core/taxonomy-store.js";
import { state } from "../core/state.js";
import {
  $,
  $$,
  closeModal,
  confirmDialog,
  empty,
  escapeHtml,
  filterBar,
  formatDate,
  hero,
  icon,
  openModal,
  panel,
  refreshIcons,
  toast,
} from "../components/ui.js?v=20260811-1";

function viewRows(items) {
  return (
    items
      .map((view) => {
        const registered = view.viewerType === "registered";
        const label = registered ? view.visitorLabel : "Anonymous visitor";
        const detail = registered
          ? view.email
          : view.sessionId || "Earlier anonymous view";
        return `<tr><td><div class="person"><div class="avatar">${icon(registered ? "user-round" : "user-round-x")}</div><div><strong>${escapeHtml(label)}</strong><small>${escapeHtml(detail)}</small></div></div></td><td><span class="badge ${registered ? "active" : "draft"}">${registered ? "Registered" : "Anonymous"}</span></td><td>${escapeHtml(view.platform || "Unknown")}</td><td>${formatDate(view.viewedAt)}</td></tr>`;
      })
      .join("") ||
    `<tr><td class="table-empty" colspan="4">${icon("inbox")}<span>No recorded viewers yet</span></td></tr>`
  );
}

function copyRows(items) {
  return (
    items
      .map((copy) => {
        const registered = copy.copierType === "registered";
        const label = registered ? copy.visitorLabel : "Anonymous visitor";
        const detail = registered
          ? copy.email
          : copy.sessionId || "Earlier anonymous copy";
        return `<tr><td><div class="person"><div class="avatar">${icon(registered ? "user-round" : "user-round-x")}</div><div><strong>${escapeHtml(label)}</strong><small>${escapeHtml(detail)}</small></div></div></td><td><span class="badge ${registered ? "active" : "draft"}">${registered ? "Registered" : "Anonymous"}</span></td><td>${escapeHtml(copy.platform || "Unknown")}</td><td>${formatDate(copy.copiedAt)}</td></tr>`;
      })
      .join("") ||
    `<tr><td class="table-empty" colspan="4">${icon("copy")}<span>No recorded prompt copies yet</span></td></tr>`
  );
}

async function openViewerDetails(image) {
  openModal(
    image.title,
    `<div class="image-viewer-loading">${icon("loader-circle")} Loading viewer activity...</div>`,
    "viewer-dialog",
  );
  try {
    const data = await api(`/admin/images/${image.id}/views?limit=100`);
    if (!$(".viewer-dialog")) return;
    $(".viewer-dialog").innerHTML =
      `<div class="modal-head"><h2>${escapeHtml(image.title)}</h2><button class="icon-button" data-close-modal aria-label="Close">${icon("x")}</button></div><img class="viewer-image" src="${escapeHtml(image.imageUrl || image.thumbnailUrl)}" alt="${escapeHtml(image.title)}"><div class="viewer-metrics"><span>${icon("eye")} ${Number(image.viewCount || 0).toLocaleString()} total views</span><span>${icon("user-round-check")} ${data.summary.registeredViews.toLocaleString()} signed-in views</span><span>${icon("user-round-x")} ${data.summary.anonymousViews.toLocaleString()} anonymous views</span><span>${icon("copy")} ${Number(image.copyCount || 0).toLocaleString()} copies</span></div><section class="viewer-activity"><div class="viewer-activity-head"><div><h3>Viewer activity</h3><p>Latest ${data.items.length.toLocaleString()} of ${data.pagination.total.toLocaleString()} recorded views</p></div></div><div class="table-wrap"><table><thead><tr><th>Viewer</th><th>Type</th><th>Platform</th><th>Viewed at</th></tr></thead><tbody>${viewRows(data.items)}</tbody></table></div></section><section class="viewer-activity copy-activity"><div class="viewer-activity-head"><div><h3>Prompt copy activity</h3><p>${data.copySummary.registeredCopies.toLocaleString()} signed-in and ${data.copySummary.anonymousCopies.toLocaleString()} anonymous copies</p></div></div><div class="table-wrap"><table><thead><tr><th>Copied by</th><th>Type</th><th>Platform</th><th>Copied at</th></tr></thead><tbody>${copyRows(data.copyItems)}</tbody></table></div></section>`;
    $$("[data-close-modal]").forEach((button) => {
      button.onclick = closeModal;
    });
    refreshIcons();
  } catch (error) {
    closeModal();
    toast(error.message, "error");
  }
}

function cards(items) {
  return (
    items
      .map(
        (image) =>
          `<article class="image-card"><button data-view-image="${image.id}" class="image-thumb-button"><img src="${escapeHtml(image.thumbnailUrl || image.imageUrl)}" data-original-src="${escapeHtml(image.imageUrl || "")}" alt="${escapeHtml(image.title)}"></button><div class="image-card-body"><small>${escapeHtml(image.category?.name || "Uncategorized")}</small><h3>${escapeHtml(image.title)}</h3><p>${escapeHtml(image.mainPrompt || "No prompt preview")}</p><div class="image-card-meta"><span class="badge ${image.status}">${image.status}</span><span>${icon("eye")} ${Number(image.viewCount || 0).toLocaleString()} views</span><span>${icon("copy")} ${Number(image.copyCount || 0).toLocaleString()} copies</span></div></div><div class="card-actions"><button class="button" data-view-image="${image.id}">${icon("users-round")} Viewers</button><button class="button" data-edit-image="${image.id}">${icon("pencil")} Edit</button><button class="button icon" data-status-image="${image.id}" title="${image.status === "published" ? "Unpublish" : "Publish"}">${icon(image.status === "published" ? "eye-off" : "check")}</button><button class="button danger icon" data-delete-image="${image.id}" title="Delete">${icon("trash-2")}</button></div></article>`,
      )
      .join("") ||
    empty("No images found", "Change the filters or add a new image.")
  );
}

function openCropper(file, onApply) {
  if (!window.Cropper) {
    toast("The crop editor could not be loaded.", "error");
    return;
  }
  const sourceUrl = URL.createObjectURL(file);
  const overlay = document.createElement("div");
  overlay.className = "cropper-backdrop";
  overlay.innerHTML = `<section class="cropper-dialog" role="dialog" aria-modal="true" aria-label="Crop artwork"><div class="cropper-head"><div><h2>Crop artwork</h2><p>Drag the crop area, resize its handles, or move the image underneath it.</p></div><button type="button" class="icon-button" data-close-crop aria-label="Close">${icon("x")}</button></div><div class="cropper-stage"><img id="crop-source" src="${sourceUrl}" alt="Artwork being cropped"></div><div class="cropper-toolbar"><label>Aspect ratio<select id="crop-ratio" class="select-control"><option value="1.6">Landscape 16:10</option><option value="1.7777778">Widescreen 16:9</option><option value="1">Square 1:1</option><option value="0.8">Portrait 4:5</option><option value="NaN">Free crop</option></select></label><div class="cropper-tool-group" aria-label="Crop tools"><button type="button" class="button icon" data-crop-action="zoom-out" title="Zoom out">${icon("zoom-out")}</button><button type="button" class="button icon" data-crop-action="zoom-in" title="Zoom in">${icon("zoom-in")}</button><button type="button" class="button icon" data-crop-action="rotate-left" title="Rotate left">${icon("rotate-ccw")}</button><button type="button" class="button icon" data-crop-action="rotate-right" title="Rotate right">${icon("rotate-cw")}</button><button type="button" class="button" data-crop-action="reset">${icon("refresh-ccw")} Reset</button></div></div><div class="form-actions cropper-footer"><button type="button" class="button" data-close-crop>Cancel</button><button type="button" class="primary" id="apply-crop">${icon("crop")} Apply crop</button></div></section>`;
  document.body.append(overlay);
  refreshIcons();

  const source = overlay.querySelector("#crop-source");
  const ratio = overlay.querySelector("#crop-ratio");
  const editor = new window.Cropper(source, {
    aspectRatio: Number(ratio.value),
    autoCropArea: 0.88,
    background: false,
    center: true,
    dragMode: "move",
    guides: true,
    highlight: true,
    responsive: true,
    restore: false,
    viewMode: 1,
  });

  const close = () => {
    editor.destroy();
    URL.revokeObjectURL(sourceUrl);
    overlay.remove();
  };
  overlay.querySelectorAll("[data-close-crop]").forEach((button) => {
    button.onclick = close;
  });
  ratio.onchange = () => editor.setAspectRatio(Number(ratio.value));
  overlay.querySelectorAll("[data-crop-action]").forEach((button) => {
    button.onclick = () => {
      const actions = {
        "zoom-out": () => editor.zoom(-0.1),
        "zoom-in": () => editor.zoom(0.1),
        "rotate-left": () => editor.rotate(-90),
        "rotate-right": () => editor.rotate(90),
        reset: () => editor.reset(),
      };
      actions[button.dataset.cropAction]?.();
    };
  });

  overlay.querySelector("#apply-crop").onclick = () => {
    editor
      .getCroppedCanvas({
        fillColor: "#fff",
        imageSmoothingEnabled: true,
        imageSmoothingQuality: "high",
        maxHeight: 1600,
        maxWidth: 1600,
      })
      .toBlob(
        (blob) => {
          if (!blob) return;
          const extension = blob.type === "image/png" ? "png" : "jpg";
          const baseName = file.name.replace(/\.[^.]+$/, "");
          onApply(
            new File([blob], `${baseName}-cropped.${extension}`, {
              type: blob.type,
            }),
          );
          close();
        },
        file.type === "image/png" ? "image/png" : "image/jpeg",
        0.92,
      );
  };
}

function editor(image, reload) {
  const selectedTags = new Set((image?.tags || []).map((tag) => tag.id));
  const shareBaseUrl = new URL("../share/", window.location.href).href;
  const initialShareMessage =
    image?.shareMessage ||
    `${image?.title || "Image title"}\n${shareBaseUrl}${image?.id || "ID"}`;
  openModal(
    image ? "Edit image & prompt" : "Add new image",
    `<form id="image-form" class="form-grid"><label>Title<input name="title" value="${escapeHtml(image?.title || "")}" minlength="2" maxlength="200" required></label><label>Category<select class="select-control" name="categoryId" required><option value="">Select category</option>${state.categories.map((category) => `<option value="${category.id}" ${category.id === image?.category?.id ? "selected" : ""}>${escapeHtml(category.name)}</option>`).join("")}</select></label><label>Status<select class="select-control" name="status"><option ${image?.status === "draft" ? "selected" : ""}>draft</option><option ${image?.status === "published" ? "selected" : ""}>published</option><option ${image?.status === "unpublished" ? "selected" : ""}>unpublished</option></select></label>${image ? "" : `<div class="span-2 artwork-field"><span class="field-label">Artwork image</span><div class="image-upload"><input id="artwork-input" name="image" type="file" accept="image/jpeg,image/png,image/gif" required><img id="artwork-preview" alt="Selected artwork preview"><div class="upload-placeholder">${icon("image-up")}<strong>Select artwork</strong><small>JPEG, PNG or GIF</small></div><div class="upload-actions"><button type="button" id="crop-artwork">${icon("crop")} Crop image</button><label for="artwork-input">${icon("refresh-cw")} Replace image</label></div></div><small id="artwork-name" class="upload-filename">No artwork selected</small></div><dialog id="upload-dialog" class="upload-dialog"><div class="upload-dialog-icon">${icon("loader-circle")}</div><h3 id="upload-dialog-title">Uploading image…</h3><p id="upload-dialog-copy">Please keep this window open while your artwork uploads.</p><div class="upload-progress-copy"><span id="upload-progress-label">Uploading image…</span><strong id="upload-percent">0%</strong></div><progress id="upload-progress-bar" max="100" value="0"></progress></dialog>`}<label class="span-2">Main prompt<textarea name="mainPrompt" required>${escapeHtml(image?.mainPrompt || "")}</textarea></label><label class="span-2">Mobile share preview<textarea id="share-preview" readonly>${escapeHtml(initialShareMessage)}</textarea></label><fieldset class="span-2"><legend>Tags</legend><div class="checks">${state.tags.map((tag) => `<label><input type="checkbox" name="tagIds" value="${tag.id}" ${selectedTags.has(tag.id) ? "checked" : ""}> ${escapeHtml(tag.name)}</label>`).join("")}</div></fieldset><div class="form-actions span-2"><button type="button" class="button" data-close-modal>Cancel</button><button class="primary">${icon("save")} Save image</button></div></form>`,
  );
  const titleInput = $("#image-form input[name='title']");
  const sharePreview = $("#share-preview");
  titleInput.oninput = () => {
    const title = titleInput.value.trim() || "Image title";
    sharePreview.value = `${title}\n${shareBaseUrl}${image?.id || "ID"}`;
  };
  if (!image) {
    const artworkInput = $("#artwork-input");
    const artworkPreview = $("#artwork-preview");
    const artworkName = $("#artwork-name");
    let previewUrl = null;

    const showArtwork = (file) => {
      if (!file) return;
      if (previewUrl) URL.revokeObjectURL(previewUrl);
      const nextPreviewUrl = URL.createObjectURL(file);
      previewUrl = nextPreviewUrl;
      artworkPreview.src = nextPreviewUrl;
      artworkPreview.onload = () => {
        URL.revokeObjectURL(nextPreviewUrl);
        if (previewUrl === nextPreviewUrl) previewUrl = null;
      };
      artworkPreview.classList.add("visible");
      artworkName.textContent = file.name;
      artworkInput.closest(".image-upload").classList.add("has-image");
    };
    artworkInput.onchange = () => showArtwork(artworkInput.files?.[0]);
    $("#crop-artwork").onclick = () => {
      const file = artworkInput.files?.[0];
      if (!file) return;
      openCropper(file, (croppedFile) => {
        const transfer = new DataTransfer();
        transfer.items.add(croppedFile);
        artworkInput.files = transfer.files;
        showArtwork(croppedFile);
      });
    };
  }
  $("#image-form").onsubmit = async (event) => {
    event.preventDefault();
    const submitButton = event.target.querySelector(
      'button[type="submit"], button.primary',
    );
    const submitButtonContent = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.classList.add("is-loading");
    submitButton.innerHTML = `${icon("loader-circle")}<span>${image ? "Saving image…" : "Uploading image…"}</span>`;
    refreshIcons();
    try {
      const form = new FormData(event.target);
      const tagIds = form.getAll("tagIds").map(Number);
      if (image) {
        const body = Object.fromEntries(form);
        body.categoryId = Number(body.categoryId);
        body.tagIds = tagIds;
        await api(`/admin/images/${image.id}`, {
          method: "PATCH",
          body: JSON.stringify(body),
        });
      } else {
        form.delete("tagIds");
        form.set("tagIds", JSON.stringify(tagIds));
        const uploadDialog = $("#upload-dialog");
        const progressBar = $("#upload-progress-bar");
        const progressPercent = $("#upload-percent");
        const progressLabel = $("#upload-progress-label");
        const dialogTitle = $("#upload-dialog-title");
        const dialogCopy = $("#upload-dialog-copy");
        uploadDialog.oncancel = (cancelEvent) => cancelEvent.preventDefault();
        uploadDialog.showModal();
        await upload("/admin/images", form, (percent) => {
          progressBar.value = percent;
          progressPercent.textContent = `${percent}%`;
          if (percent === 100) {
            progressLabel.textContent = "Processing image…";
            dialogTitle.textContent = "Processing image…";
            dialogCopy.textContent =
              "The upload is complete. We are preparing your artwork.";
            submitButton.querySelector("span").textContent =
              "Processing image…";
          }
        });
      }
      closeModal();
      toast(image ? "Image updated." : "Image created.");
      await reload();
    } catch (error) {
      const uploadDialog = $("#upload-dialog");
      if (uploadDialog?.open) uploadDialog.close();
      toast(error.message, "error");
      submitButton.disabled = false;
      submitButton.classList.remove("is-loading");
      submitButton.innerHTML = submitButtonContent;
      refreshIcons();
    }
  };
  refreshIcons();
}

function bindActions(items, reload) {
  $$(".image-thumb-button img").forEach((thumbnail) => {
    thumbnail.onerror = () => {
      const originalUrl = thumbnail.dataset.originalSrc;
      if (originalUrl && thumbnail.src !== originalUrl) {
        thumbnail.src = originalUrl;
      }
    };
  });
  $$("[data-view-image]").forEach((button) => {
    button.onclick = () => {
      const image = items.find(
        (item) => item.id === Number(button.dataset.viewImage),
      );
      openViewerDetails(image);
    };
  });
  $$("[data-edit-image]").forEach((button) => {
    button.onclick = () =>
      editor(
        items.find((item) => item.id === Number(button.dataset.editImage)),
        reload,
      );
  });
  $$("[data-status-image]").forEach((button) => {
    button.onclick = async () => {
      const image = items.find(
        (item) => item.id === Number(button.dataset.statusImage),
      );
      const status = image.status === "published" ? "unpublished" : "published";
      await api(`/admin/images/${image.id}`, {
        method: "PATCH",
        body: JSON.stringify({ status }),
      });
      toast(`Image ${status}.`);
      await reload();
    };
  });
  $$("[data-delete-image]").forEach((button) => {
    button.onclick = async () => {
      const confirmed = await confirmDialog({
        title: "Delete image?",
        message:
          "This permanently removes the image, prompt data, and uploaded files.",
        confirmLabel: "Delete image",
        tone: "danger",
        iconName: "trash-2",
      });
      if (!confirmed) return;
      await api(`/admin/images/${button.dataset.deleteImage}`, {
        method: "DELETE",
      });
      toast("Image deleted.");
      await reload();
    };
  });
  refreshIcons();
}

export const imagesPage = {
  id: "images",
  label: "Images & prompts",
  icon: "file-image",
  kicker: "CONTENT",
  async render() {
    await loadTaxonomies();
    const data = await api("/admin/images?limit=100");
    $("#content").innerHTML =
      hero(
        "Images & prompts",
        "Browse, filter, publish, and manage creative assets.",
        `<button id="add-image" class="primary">${icon("image-plus")} Add image</button>`,
      ) +
      panel(
        filterBar(
          `<label>Search<input id="image-search" placeholder="Search title or prompt"></label><label>Category<select id="image-category" class="select-control"><option value="">All categories</option>${state.categories.map((category) => `<option value="${category.id}">${escapeHtml(category.name)}</option>`).join("")}</select></label><label>Status<select id="image-status" class="select-control"><option value="">All statuses</option><option>draft</option><option>published</option><option>unpublished</option></select></label>`,
        ) +
          `<div id="image-cards" class="cards image-grid-spacing">${cards(data.items)}</div>`,
      );
    const filter = () => {
      const query = $("#image-search").value.toLowerCase();
      const category = $("#image-category").value;
      const status = $("#image-status").value;
      const items = data.items.filter(
        (image) =>
          (!query ||
            `${image.title} ${image.mainPrompt || ""}`
              .toLowerCase()
              .includes(query)) &&
          (!category ||
            String(image.category?.id || image.category_id) === category) &&
          (!status || image.status === status),
      );
      $("#image-cards").innerHTML = cards(items);
      bindActions(data.items, () => this.render());
    };
    $("#add-image").onclick = () => editor(null, () => this.render());
    $("#image-search").oninput = filter;
    $("#image-category").onchange = filter;
    $("#image-status").onchange = filter;
    bindActions(data.items, () => this.render());
  },
};
