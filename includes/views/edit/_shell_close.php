<?php
/**
 * Partial: _shell_close.php
 *
 * Fecha o "casco" da página de edição.
 * O conteúdo da sidebar é passado como HTML pré-renderizado em $sidebar_tips_html
 * (cada view específica define isto antes de incluir este partial).
 *
 * Variáveis esperadas:
 *   @var bool   $is_ajax
 *   @var string $sidebar_tips_html
 */
?>
<?php if ($is_ajax): ?>
    </div><!-- /.edit-modal-body -->
<?php else: ?>
            </section>
        </main>
        <aside class="right-sidebar">
            <div class="sidebar-section">
                <?= $sidebar_tips_html ?>
            </div>
        </aside>
    </div><!-- /.main-layout-container -->
<?php endif; ?>
