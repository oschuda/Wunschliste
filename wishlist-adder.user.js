// ==UserScript==
// @name         🛒 Wunschliste: Artikel hinzufügen (Premium)
// @namespace    WishlistApp
// @version      2.0
// @description  Fügt Artikel von Amazon, Ebay, Temu, Banggood etc. deiner Wunschliste hinzu – inkl. Preis-Erkennung.
// @author       Wishlist Admin
// @match        *://*/*
// @grant        GM_xmlhttpRequest
// @connect      *
// ==/UserScript==

(function() {
    "use strict";

    const CONFIG = {
        API_KEY: "DEIN_KEY_HIER",
        API_URL: "https://192.168.178.30/geschenke/api-add.php"
    };

    const btn = document.createElement("button");
    btn.id = "wishlist-adder-btn";
    btn.innerHTML = "<span>📂</span> Merkliste";
    btn.setAttribute("style", "position:fixed; bottom:20px; right:20px; z-index:2147483647; padding:12px 24px; background:#2c3e50; color:#ecf0f1; border:2px solid #34495e; border-radius:50px; cursor:pointer; font-family:sans-serif; font-size:14px; font-weight:bold; box-shadow:0 4px 15px rgba(0,0,0,0.4); display:flex; align-items:center; gap:8px; transition:all 0.3s ease;");
    
    btn.onmouseover = () => { btn.style.transform = "scale(1.05)"; btn.style.background = "#34495e"; };
    btn.onmouseout  = () => { btn.style.transform = "scale(1)"; btn.style.background = "#2c3e50"; };
    
    document.body.appendChild(btn);

    function extractPrice() {
        let price = 0;
        try {
            const jsonLd = document.querySelectorAll('script[type="application/ld+json"]');
            for (let script of jsonLd) {
                const data = JSON.parse(script.innerText);
                const offer = data.offers;
                if (offer) {
                   const p = Array.isArray(offer) ? offer[0].price : offer.price;
                   if (p) return parseFloat(String(p).replace(",", "."));
                }
            }
        } catch (e) {}
        const metaPrice = document.querySelector('meta[property="product:price:amount"]');
        if (metaPrice) return parseFloat(metaPrice.getAttribute("content").replace(",", "."));
        const selectors = ['.a-price-whole', '#prcIsum', '.product-price', '.main_price', '[itemprop="price"]'];
        for (let s of selectors) {
            let el = document.querySelector(s);
            if (el) {
                let cleaned = el.innerText.trim().replace(/[^\d,\.]/g, "").replace(",", ".");
                let val = parseFloat(cleaned);
                if (val > 0) return val;
            }
        }
        return 0;
    }

    btn.onclick = function() {
        if (CONFIG.API_KEY === "DEIN_KEY_HIER") { alert("Bitte trage zuerst deinen API-Key im Script ein!"); return; }
        const originalText = btn.innerHTML;
        btn.innerHTML = "<span>⌛</span> Analysiere...";
        btn.disabled = true;
        const itemData = {
            title: document.title.split("-")[0].split("|")[0].trim(),
            url: window.location.href,
            price: extractPrice()
        };
        GM_xmlhttpRequest({
            method: "POST", url: CONFIG.API_URL, data: JSON.stringify(itemData),
            headers: { "Content-Type": "application/json", "X-Wishlist-Key": CONFIG.API_KEY },
            anonymous: true,
            onload: function(response) {
                btn.disabled = false;
                try {
                    const res = JSON.parse(response.responseText);
                    if (res.success) { btn.innerHTML = "<span>✅</span> Hinzugefügt!"; btn.style.background = "#27ae60"; }
                    else { btn.innerHTML = "<span>❌</span> Fehler"; btn.style.background = "#e74c3c"; }
                } catch(e) { btn.innerHTML = "<span>❌</span> Error"; }
                setTimeout(() => { btn.innerHTML = originalText; btn.style.background = "#2c3e50"; }, 3000);
            }
        });
    };
})();