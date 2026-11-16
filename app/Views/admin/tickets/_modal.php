<?php
/**
 * Kerangka modal detail tiket di daftar tiket. Isi kartunya diambil lewat fetch (TicketController::show untuk AJAX)
 * dan dipasang oleh js/pages/admin/ticket-modal.js.
 */
?>
<div class="modal ticket-modal" id="ticketModal">
    <div class="modal-background" data-modal-close></div>
    <div class="ticket-modal-card" role="dialog" aria-modal="true" aria-labelledby="ticketModalTitle" aria-busy="true" tabindex="-1" data-ticket-modal-content></div>
</div>
