<?php
/**
 * Ergänzung für QR-Code Modal Funktionalität
 */

function getQRModalHtml(): string {
    require_once __DIR__ . '/i18n.php';
    return '
    <div id="qrModal" class="qr-modal" onclick="this.classList.remove(\'active\')">
        <img id="qrModalImg" src="" alt="QR-Code">
        <div class="close-hint">' . translate("SCHLIESSEN") . '</div>
    </div>
    <script>
    function showQRModal(src) {
        document.getElementById("qrModalImg").src = src;
        document.getElementById("qrModal").classList.add("active");
    }
    </script>';
}
