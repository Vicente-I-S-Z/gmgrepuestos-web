<?php
/**
 * Piezas que se repiten en todas las páginas públicas de la tienda:
 *   1. Botón y ventana del Asistente Virtual (chat flotante).
 *   2. Panel lateral del carrito.
 *   3. Formulario de datos del cliente antes de pagar.
 *
 * Se incluye desde backend/pagina_publica.php (función gmgPiePublico).
 * Variables esperadas: $modelosCortosTexto (texto con los modelos del catálogo).
 */
$modelosCortosTexto = $modelosCortosTexto ?? 'todos los modelos Suzuki';
?>
    <!-- Botón Flotante del Chatbot IA (historia E5-H1: visible en cualquier página pública) -->
    <div id="gmgChatToggle" class="gmg-chat-toggle" role="button" tabindex="0" aria-label="Abrir Asistente Virtual GMG" title="Abrir Asistente Virtual GMG">
        <span class="chat-badge"></span>
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
        </svg>
    </div>

    <!-- Ventana del Chatbot IA -->
    <div id="gmgChatWindow" class="gmg-chat-window" role="dialog" aria-label="Asistente Virtual GMG">
        <div class="gmg-chat-header">
            <div class="gmg-chat-header-info">
                <div class="gmg-chat-avatar">GMG</div>
                <div class="gmg-chat-title">
                    <h4>Asistente GMG (IA)</h4>
                    <p>Especialista en línea</p>
                </div>
            </div>
            <div class="gmg-chat-header-actions">
                <button id="gmgChatClose" class="gmg-chat-close-btn" title="Minimizar chat" aria-label="Minimizar chat">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>

        <div id="gmgChatMessages" class="gmg-chat-messages" aria-live="polite">
            <div class="gmg-msg gmg-msg-bot">
                <div class="gmg-msg-bubble">
                    <p>¡Hola! 👋 Soy tu <strong>Asistente Virtual GMG</strong> especialista en repuestos Suzuki (<?php echo htmlspecialchars($modelosCortosTexto); ?>).</p>
                    <p>¿En qué te puedo ayudar hoy? Puedes consultarme por precios, disponibilidad, subir hasta <strong>3 fotos</strong> de la pieza que necesitas, o dictar tu consulta por voz.</p>
                </div>
                <span class="gmg-msg-time">Ahora</span>
            </div>
        </div>

        <div id="gmgChatAviso" class="gmg-chat-aviso" role="status" aria-live="polite" hidden></div>

        <!-- Botones rápidos (E5-H1). Los que llevan data-atajo se responden al instante con la información configurada en el panel. -->
        <div class="gmg-quick-chips">
            <button type="button" class="gmg-chip" data-atajo="buscar" data-prompt="Buscar repuesto">🔍 Buscar repuesto</button>
            <button type="button" class="gmg-chip" data-atajo="despacho" data-prompt="Despacho">🚚 Despacho</button>
            <button type="button" class="gmg-chip" data-atajo="pagos" data-prompt="Medios de pago">💳 Medios de pago</button>
            <button type="button" class="gmg-chip" data-action="upload" data-prompt="Subir foto">📸 Enviar foto</button>
        </div>

        <div id="gmgChatPreviewBar" class="gmg-chat-image-preview-bar">
            <div id="gmgChatPreviewList" class="gmg-chat-preview-list"></div>
        </div>

        <div class="gmg-chat-footer">
            <input type="file" id="gmgChatFileInput" accept="image/*" multiple style="display: none;">
            <button type="button" id="gmgChatFileBtn" class="gmg-chat-file-btn" title="Adjuntar hasta 3 fotos de la pieza" aria-label="Adjuntar fotos de la pieza">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                    <circle cx="12" cy="13" r="4"></circle>
                </svg>
            </button>
            <button type="button" id="gmgChatMicBtn" class="gmg-chat-file-btn gmg-chat-mic-btn" title="Consultar por voz" aria-label="Consultar por voz" style="display:none;">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                    <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                    <line x1="12" y1="19" x2="12" y2="23"></line>
                    <line x1="8" y1="23" x2="16" y2="23"></line>
                </svg>
            </button>
            <input type="text" id="gmgChatInput" class="gmg-chat-input" placeholder="Escribe tu consulta..." autocomplete="off" aria-label="Escribe tu consulta">
            <button type="button" id="gmgChatSend" class="gmg-chat-send-btn" title="Enviar mensaje" aria-label="Enviar mensaje">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path>
                </svg>
            </button>
        </div>
    </div>

    <!-- Panel Lateral del Carrito -->
    <div id="cart-sidebar" class="cart-sidebar">
        <div class="cart-header">
            <h3>Tu Carrito</h3>
            <button id="close-cart" class="close-cart" aria-label="Cerrar carrito">&times;</button>
        </div>
        <div class="cart-select-all-row" id="cartSelectAllRow" style="display:none;">
            <label class="cart-checkbox-label">
                <input type="checkbox" id="cartSelectAll" checked>
                Seleccionar todos
            </label>
        </div>
        <div id="cart-items" class="cart-items"></div>
        <div id="cart-guardados" class="cart-guardados"></div>
        <div id="cart-relacionados" class="cart-relacionados"></div>
        <div class="cart-footer">
            <div class="cart-promo">
                <input type="text" id="promoInput" placeholder="Código de descuento" autocomplete="off" aria-label="Código de descuento">
                <button type="button" id="btnAplicarPromo">Aplicar</button>
            </div>
            <div id="promoMensaje" class="cart-promo-msg" role="status" aria-live="polite"></div>
            <div class="cart-subtotal-row" id="cartSubtotalRow" style="display:none;">
                <span>Subtotal:</span><span id="cart-subtotal-price">$0</span>
            </div>
            <div class="cart-descuento-row" id="cartDescuentoRow" style="display:none;">
                <span>Descuento:</span><span id="cart-descuento-price">-$0</span>
            </div>
            <!-- Ley 19.496 (E9-H1): el cliente ve el despacho y las políticas antes de pagar -->
            <div class="cart-despacho-row">
                <span>Despacho:</span><span>Se coordina por WhatsApp</span>
            </div>
            <div class="cart-total">
                <span>Total a pagar:</span>
                <span id="cart-total-price">$0</span>
            </div>
            <p class="cart-legal">Precios en pesos chilenos (CLP). El pago en línea cubre solo los repuestos; el despacho no tiene cobro en este pago y se coordina contigo por WhatsApp. Revisa nuestras políticas de <a href="cambios-y-devoluciones.php">cambios y devoluciones</a>, <a href="cambios-y-devoluciones.php#garantia-legal">garantía legal</a> y <a href="cambios-y-devoluciones.php#derecho-de-retracto">derecho de retracto</a>.</p>
            <button id="btn-checkout" class="btn-checkout btn-mercadopago">Pagar con Mercado Pago</button>
            <div id="checkout-spinner" class="cart-spinner" role="status" aria-live="polite" style="display:none;">Procesando...</div>
        </div>
    </div>
    <div id="cart-overlay" class="cart-overlay"></div>

    <!-- Modal de datos del cliente (previo al pago) -->
    <div id="clienteModalOverlay" class="cliente-modal-overlay">
        <div class="cliente-modal" role="dialog" aria-modal="true" aria-labelledby="clienteModalTitulo">
            <button type="button" id="clienteModalClose" class="cliente-modal-close" aria-label="Cerrar">&times;</button>
            <h3 id="clienteModalTitulo">Un último paso</h3>
            <p class="cliente-modal-sub">Necesitamos estos datos para coordinar tu compra. GMG Repuestos no realiza envíos: coordinamos contigo la entrega por WhatsApp.</p>
            <div class="cliente-resumen" id="clienteResumen" aria-live="polite"></div>
            <form id="clienteForm" class="cliente-form" novalidate>
                <div class="cliente-form-row">
                    <div class="cliente-campo">
                        <label for="clienteNombre">Nombre</label>
                        <input type="text" id="clienteNombre" autocomplete="given-name" aria-describedby="errClienteNombre">
                        <small class="campo-error" id="errClienteNombre" role="alert"></small>
                    </div>
                    <div class="cliente-campo">
                        <label for="clienteApellido">Apellido</label>
                        <input type="text" id="clienteApellido" autocomplete="family-name" aria-describedby="errClienteApellido">
                        <small class="campo-error" id="errClienteApellido" role="alert"></small>
                    </div>
                </div>
                <div class="cliente-form-row">
                    <div class="cliente-campo">
                        <label for="clienteRut">RUT</label>
                        <input type="text" id="clienteRut" placeholder="12.345.678-9" inputmode="text" autocomplete="off" aria-describedby="errClienteRut">
                        <small class="campo-error" id="errClienteRut" role="alert"></small>
                    </div>
                    <div class="cliente-campo">
                        <label for="clienteTelefono">Teléfono</label>
                        <input type="tel" id="clienteTelefono" placeholder="+56 9 1234 5678" autocomplete="tel" aria-describedby="errClienteTelefono">
                        <small class="campo-error" id="errClienteTelefono" role="alert"></small>
                    </div>
                </div>
                <div class="cliente-form-row">
                    <div class="cliente-form-full cliente-campo">
                        <label for="clienteEmail">Correo electrónico</label>
                        <input type="email" id="clienteEmail" placeholder="tucorreo@ejemplo.cl" autocomplete="email" aria-describedby="errClienteEmail">
                        <small class="campo-error" id="errClienteEmail" role="alert"></small>
                    </div>
                </div>
                <p class="cliente-form-nota">Te avisaremos por este correo cuando tu pedido sea aprobado o si hay algún problema con el pago.</p>
                <div class="cliente-campo cliente-acepta">
                    <label class="cliente-acepta-label" for="clienteAcepta">
                        <input type="checkbox" id="clienteAcepta" aria-describedby="errClienteAcepta">
                        <span>Leí y acepto los <a href="terminos-y-condiciones.php" target="_blank" rel="noopener">Términos y condiciones</a> y la <a href="politica-de-privacidad.php" target="_blank" rel="noopener">Política de privacidad</a>. Sé que tengo <a href="cambios-y-devoluciones.php#derecho-de-retracto" target="_blank" rel="noopener">derecho de retracto</a> y <a href="cambios-y-devoluciones.php#garantia-legal" target="_blank" rel="noopener">garantía legal</a>.</span>
                    </label>
                    <small class="campo-error" id="errClienteAcepta" role="alert"></small>
                </div>
                <div id="clienteFormError" class="cliente-form-error" role="alert" style="display:none;"></div>
                <button type="submit" class="btn-checkout" id="btnConfirmarCliente">Continuar al pago</button>
                <div id="clienteFormSpinner" class="cart-spinner" role="status" aria-live="polite" style="display:none;">Procesando...</div>
            </form>
        </div>
    </div>

    <!-- Avisos breves de éxito o error (E9-H7) -->
    <div id="gmgToast" class="gmg-toast" role="status" aria-live="polite" hidden></div>
