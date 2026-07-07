(function () {
  function initLiteGallery(gallery) {
    if (!gallery || gallery.dataset.linsyLiteGallery === "ready") {
      return;
    }

    var items = Array.prototype.slice.call(
      gallery.querySelectorAll(".woocommerce-product-gallery__wrapper > .woocommerce-product-gallery__image")
    );

    if (items.length <= 1) {
      return;
    }

    var mainItem = items[0];
    var mainLink = mainItem.querySelector("a");
    var mainImg = mainItem.querySelector("img");

    if (!mainLink || !mainImg) {
      return;
    }

    items.forEach(function (item, index) {
      item.classList.toggle("is-active", index === 0);
    });

    var thumbs = document.createElement("ol");
    thumbs.className = "linsy-product-gallery-thumbs";

    function setActive(index) {
      var sourceItem = items[index];
      var sourceLink = sourceItem.querySelector("a");
      var sourceImg = sourceItem.querySelector("img");
      var largeSrc = sourceImg && sourceImg.getAttribute("data-large_image");
      var nextSrc = largeSrc || (sourceLink ? sourceLink.getAttribute("href") : null) || (sourceImg ? sourceImg.getAttribute("src") : null);

      if (!sourceImg || !nextSrc) {
        return;
      }

      items.forEach(function (item, itemIndex) {
        item.classList.toggle("is-active", itemIndex === index);
      });

      Array.prototype.forEach.call(thumbs.querySelectorAll("button"), function (button, buttonIndex) {
        button.classList.toggle("is-active", buttonIndex === index);
        button.setAttribute("aria-pressed", buttonIndex === index ? "true" : "false");
      });

      mainLink.setAttribute("href", sourceLink ? sourceLink.getAttribute("href") : nextSrc);
      mainImg.setAttribute("src", nextSrc);
      mainImg.setAttribute("alt", sourceImg.getAttribute("alt") || "");
      mainImg.removeAttribute("srcset");
      mainImg.removeAttribute("sizes");

      if (sourceImg.getAttribute("data-large_image_width")) {
        mainImg.setAttribute("width", sourceImg.getAttribute("data-large_image_width"));
      }

      if (sourceImg.getAttribute("data-large_image_height")) {
        mainImg.setAttribute("height", sourceImg.getAttribute("data-large_image_height"));
      }
    }

    items.forEach(function (item, index) {
      var thumbSrc = item.getAttribute("data-thumb");
      var thumbAlt = item.getAttribute("data-thumb-alt") || "Product image " + (index + 1);

      if (!thumbSrc) {
        return;
      }

      var li = document.createElement("li");
      var button = document.createElement("button");
      button.type = "button";
      button.className = index === 0 ? "is-active" : "";
      button.setAttribute("aria-label", "Show product image " + (index + 1));
      button.setAttribute("aria-pressed", index === 0 ? "true" : "false");

      var img = document.createElement("img");
      img.src = thumbSrc;
      img.alt = thumbAlt;
      img.loading = "lazy";
      img.decoding = "async";

      button.appendChild(img);
      button.addEventListener("click", function () {
        setActive(index);
      });

      li.appendChild(button);
      thumbs.appendChild(li);
    });

    if (thumbs.children.length) {
      gallery.appendChild(thumbs);
      gallery.dataset.linsyLiteGallery = "ready";
    }
  }

  function boot() {
    var galleries = document.querySelectorAll(".woocommerce-product-gallery");
    Array.prototype.forEach.call(galleries, initLiteGallery);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
