<?php
/**
 * Vista HTML de la orden (solo lectura), misma estructura visual que el formulario.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param array<string, mixed> $data
 * @param array{readonly?:bool} $opts
 */
function pd_imaging_render_order_view(array $data, array $opts = []): void
{
    $readonly = !empty($opts['readonly']);
    $dis = $readonly ? ' disabled' : '';

    $val = static function (string $key) use ($data): string {
        return isset($data[$key]) ? esc_attr((string) $data[$key]) : '';
    };

    $area = static function (string $key) use ($data): string {
        return isset($data[$key]) ? esc_textarea((string) $data[$key]) : '';
    };

    $chk = static function (string $key, string $expect = 'SI') use ($data, $dis): string {
        $on = isset($data[$key]) && (string) $data[$key] === $expect;
        return ($on ? ' checked' : '') . $dis;
    };

    $rad = static function (string $key, string $value) use ($data, $dis): string {
        $on = isset($data[$key]) && (string) $data[$key] === $value;
        return ($on ? ' checked' : '') . $dis;
    };

    $tooth = static function (string $field) use ($data, $dis): string {
        return (!empty($data[$field]) ? ' checked' : '') . $dis;
    };

    $sex_m = $rad('paciente_sexo', 'M');
    $sex_f = $rad('paciente_sexo', 'F');

    $logo = 'https://pd-imaging.com/wp-content/uploads/2026/04/pd-imaging-centro-de-imagenes-logo.png';

    $tomo_left_rows = [
        ['18', '17', '16', '15', '14', '13', '12', '11'],
        ['48', '47', '46', '45', '44', '43', '42', '41'],
    ];
    $tomo_right_rows = [
        ['21', '22', '23', '24', '25', '26', '27', '28'],
        ['31', '32', '33', '34', '35', '36', '37', '38'],
    ];

    $rx_left_rows = $tomo_left_rows;
    $rx_right_rows = $tomo_right_rows;

    $render_tomo_rows = static function (array $rows) use ($data, $tooth): void {
        foreach ($rows as $row) {
            echo '<div class="pd-odonto-row">';
            foreach ($row as $n) {
                $f = 'tomo3d_diente_' . $n;
                $lbl = isset($data[$f]) ? esc_html((string) $data[$f]) : esc_html($n);
                echo '<label class="pd-tooth"><input type="checkbox"' . $tooth($f) . '><div class="pd-tooth-box">' . $lbl . '</div></label>';
            }
            echo '</div>';
        }
    };

    $render_rx_rows = static function (array $rows) use ($data, $tooth): void {
        foreach ($rows as $row) {
            echo '<div class="pd-odonto-row">';
            foreach ($row as $n) {
                $f = 'rxintra_diente_' . $n;
                $lbl = isset($data[$f]) ? esc_html((string) $data[$f]) : esc_html($n);
                echo '<label class="pd-tooth"><input type="checkbox"' . $tooth($f) . '><div class="pd-tooth-box pd-tooth-box-sm">' . $lbl . '</div></label>';
            }
            echo '</div>';
        }
    };

    ?>
<section class="pd-form-wrapper pd-order-view">
    <div class="pd-form-container">

        <div class="pd-header">
            <div class="pd-logo-container">
                <img src="<?php echo esc_url($logo); ?>" alt="PD Imaging Centro de Imágenes" class="pd-logo" width="280" height="80" loading="lazy">
            </div>
            <div class="pd-social">
                <div class="pd-social-links">
                    <a href="#" class="pd-social-link" aria-label="TikTok" tabindex="-1">
                        <svg viewBox="0 0 448 512" aria-hidden="true"><path d="M448,209.91a210.06,210.06,0,0,1-122.77-39.25V349.38A162.55,162.55,0,1,1,185,188.31V278.2a74.62,74.62,0,1,0,52.23,71.18V0l88,0a121.18,121.18,0,0,0,1.86,22.17h0A122.18,122.18,0,0,0,381,102.39a121.43,121.43,0,0,0,67,20.14Z"/></svg>
                    </a>
                    <a href="#" class="pd-social-link" aria-label="Facebook" tabindex="-1">
                        <svg viewBox="0 0 320 512" aria-hidden="true"><path d="M279.14 288l14.22-92.66h-88.91v-60.13c0-25.35 12.42-50.06 52.24-50.06h40.42V6.26S260.43 0 225.36 0c-73.22 0-121.08 44.38-121.08 124.72v70.62H22.89V288h81.39v224h100.17V288z"/></svg>
                    </a>
                    <a href="#" class="pd-social-link" aria-label="Instagram" tabindex="-1">
                        <svg viewBox="0 0 448 512" aria-hidden="true"><path d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z"/></svg>
                    </a>
                    <a href="#" class="pd-social-link" aria-label="LinkedIn" tabindex="-1">
                        <svg viewBox="0 0 448 512" aria-hidden="true"><path d="M100.28 448H7.4V148.9h92.88zM53.79 108.1C24.09 108.1 0 83.5 0 53.8a53.79,53.79,0,0,1,107.58,0c0,29.7-24.1,54.3-53.79,54.3zM447.9 448h-92.68V302.4c0-34.7-.7-79.2-48.29-79.2-48.29,0-55.69,37.7-55.69,76.7V448h-92.78V148.9h89.08v40.8h1.3c12.4-23.5 42.69-48.3 87.88-48.3 94,0,111.28,61.9,111.28,142.3V448z"/></svg>
                    </a>
                </div>
                <span class="pd-handle">@pdimaging</span>
            </div>
        </div>

        <div class="pd-grid-datos">
            <div class="pd-box">
                <div class="pd-field-row">
                    <span class="pd-label">Paciente:</span>
                    <input type="text" class="pd-input-line" readonly value="<?php echo $val('paciente_nombre'); ?>">
                </div>
                <div class="pd-grid-2">
                    <div class="pd-field-row">
                        <span class="pd-label">Edad:</span>
                        <input type="text" class="pd-input-line" readonly value="<?php echo $val('paciente_edad'); ?>">
                    </div>
                    <div class="pd-field-row">
                        <span class="pd-label">Fecha de nacimiento:</span>
                        <input type="text" class="pd-input-line" readonly value="<?php echo $val('paciente_fecha_nacimiento'); ?>">
                    </div>
                </div>
                <div class="pd-grid-2" style="align-items:flex-end;">
                    <div class="pd-field-row">
                        <span class="pd-label">DNI / Carnet:</span>
                        <input type="text" class="pd-input-line" readonly value="<?php echo $val('paciente_dni'); ?>">
                    </div>
                    <div class="pd-sexo-group">
                        <span class="pd-label" style="white-space: nowrap;">Sexo:</span>
                        <div class="pd-sexo-options">
                            <label style="position:relative;">
                                <input type="radio" class="pd-sexo-input"<?php echo $sex_m; ?>>
                                <span class="pd-sexo-label">M</span>
                            </label>
                            <label style="position:relative;">
                                <input type="radio" class="pd-sexo-input"<?php echo $sex_f; ?>>
                                <span class="pd-sexo-label">F</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="pd-grid-2">
                    <div class="pd-field-row">
                        <span class="pd-label">Celular:</span>
                        <input type="text" class="pd-input-line" readonly value="<?php echo $val('paciente_celular'); ?>">
                    </div>
                    <div class="pd-field-row">
                        <span class="pd-label">Correo:</span>
                        <input type="text" class="pd-input-line" readonly value="<?php echo $val('paciente_correo'); ?>">
                    </div>
                </div>
                <div class="pd-field-row">
                    <span class="pd-label">Motivo de consulta:</span>
                    <input type="text" class="pd-input-line" readonly value="<?php echo $val('motivo_consulta'); ?>">
                </div>
            </div>

            <div class="pd-box" style="display:flex; flex-direction:column; justify-content:center;">
                <div class="pd-field-row">
                    <span class="pd-label">Doctor:</span>
                    <input type="text" class="pd-input-line" readonly value="<?php echo $val('doctor_nombre'); ?>">
                </div>
                <div class="pd-grid-2">
                    <div class="pd-field-row">
                        <span class="pd-label">COP:</span>
                        <input type="text" class="pd-input-line" readonly value="<?php echo $val('doctor_cop'); ?>">
                    </div>
                    <div class="pd-field-row">
                        <span class="pd-label">Celular:</span>
                        <input type="text" class="pd-input-line" readonly value="<?php echo $val('doctor_celular'); ?>">
                    </div>
                </div>
                <div class="pd-field-row">
                    <span class="pd-label">Correo:</span>
                    <input type="text" class="pd-input-line" readonly value="<?php echo $val('doctor_correo'); ?>">
                </div>
                <div class="pd-field-row">
                    <span class="pd-label">Dirección:</span>
                    <input type="text" class="pd-input-line" readonly value="<?php echo $val('doctor_direccion'); ?>">
                </div>
            </div>
        </div>

        <div class="pd-grid-clinico">

            <div class="pd-col-izq">

                <div class="pd-card">
                    <div class="pd-card-header">
                        <span>Tomografías 3D</span>
                        <div class="pd-card-options">
                            <label><input type="checkbox" class="pd-checkbox"<?php echo $chk('tomo3d_con_informe'); ?>> C/ INFORME</label>
                            <label><input type="checkbox" class="pd-checkbox"<?php echo $chk('tomo3d_digital'); ?>> DIGITAL</label>
                        </div>
                    </div>
                    <div class="pd-card-body pd-space-y-6">
                        <div class="pd-check-group-inline">
                            <label><input type="checkbox" class="pd-checkbox"<?php echo $chk('tomo3d_implantes'); ?>> Implantes</label>
                            <div class="pd-flex-col" style="gap:0.75rem;">
                                <label><input type="radio" name="_pd_view_max" value="superior" class="pd-radio"<?php echo $rad('tomo3d_maxilar', 'superior'); ?>> Maxilar superior</label>
                                <label><input type="radio" name="_pd_view_max" value="inferior" class="pd-radio"<?php echo $rad('tomo3d_maxilar', 'inferior'); ?>> Maxilar inferior</label>
                            </div>
                        </div>

                        <div class="pd-odonto-container pd-scroll">
                            <div class="pd-odonto">
                                <div class="pd-odonto-label">D</div>
                                <div class="pd-odonto-rows">
                                    <?php $render_tomo_rows($tomo_left_rows); ?>
                                </div>
                                <div class="pd-odonto-divider"></div>
                                <div class="pd-odonto-rows">
                                    <?php $render_tomo_rows($tomo_right_rows); ?>
                                </div>
                                <div class="pd-odonto-label">I</div>
                            </div>
                        </div>

                        <div class="pd-space-y-4 pd-pt-2">
                            <div class="pd-field-combo">
                                <label class="pd-label pd-whitespace-nowrap pd-flex-col" style="gap:0.25rem;">
                                    <span style="display:flex; align-items:center; gap:0.5rem;"><input type="checkbox" class="pd-checkbox"<?php echo $chk('tomo3d_check_localizacion'); ?>> Localización de Pieza Dentaria:</span>
                                </label>
                                <textarea rows="2" class="pd-textarea" readonly><?php echo $area('tomo3d_txt_localizacion'); ?></textarea>
                            </div>
                            <div class="pd-field-combo">
                                <label class="pd-label pd-whitespace-nowrap pd-flex-col" style="gap:0.25rem;">
                                    <span style="display:flex; align-items:center; gap:0.5rem;"><input type="checkbox" class="pd-checkbox"<?php echo $chk('tomo3d_check_eval_patologica'); ?>> Evaluación de área patológica:</span>
                                </label>
                                <textarea rows="2" class="pd-textarea" readonly><?php echo $area('tomo3d_txt_eval_patologica'); ?></textarea>
                            </div>
                            <div class="pd-field-combo pd-mt-4">
                                <span class="pd-label pd-whitespace-nowrap">Evaluación y especificaciones:</span>
                                <textarea rows="2" class="pd-textarea" readonly><?php echo $area('tomo3d_txt_especificaciones'); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pd-card">
                    <div class="pd-card-header">
                        <span>Radiografía Extraoral</span>
                        <div class="pd-card-options">
                            <label><input type="checkbox" class="pd-checkbox"<?php echo $chk('rx_extra_con_informe'); ?>> C/ INFORME</label>
                            <label><input type="checkbox" class="pd-checkbox"<?php echo $chk('rx_extra_digital'); ?>> DIGITAL</label>
                        </div>
                    </div>
                    <div class="pd-card-body pd-space-y-6">
                        <div class="pd-check-grid">
                            <label style="grid-column: span 2;"><input type="checkbox" class="pd-checkbox"<?php echo $chk('rx_extra_panoramica'); ?>> Panorámica</label>
                            <label><input type="checkbox" class="pd-checkbox"<?php echo $chk('rx_extra_lateral'); ?>> Lateral</label>
                            <label><input type="checkbox" class="pd-checkbox"<?php echo $chk('rx_extra_lateral_estricta'); ?>> Lateral Estricta</label>
                            <label><input type="checkbox" class="pd-checkbox"<?php echo $chk('rx_extra_pos_nat_cabeza'); ?>> Posición Nat. de Cabeza</label>
                            <label><input type="checkbox" class="pd-checkbox"<?php echo $chk('rx_extra_postero_anterior'); ?>> Póstero - Anterior</label>
                            <label><input type="checkbox" class="pd-checkbox"<?php echo $chk('rx_extra_carpal'); ?>> Carpal</label>
                            <label><input type="checkbox" class="pd-checkbox"<?php echo $chk('rx_extra_cavum'); ?>> Cavum Faríngeo</label>
                            <label><input type="checkbox" class="pd-checkbox"<?php echo $chk('rx_extra_7ma_vertebra'); ?>> 7ma. Vértebra</label>
                        </div>
                        <div class="pd-field-combo pd-pt-2 pd-border-t">
                            <span class="pd-label pd-whitespace-nowrap">Evaluación y especificaciones:</span>
                            <textarea rows="2" class="pd-textarea" readonly><?php echo $area('rx_extra_especificaciones'); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="pd-card">
                    <div class="pd-card-header">
                        <span>Radiografía Intraorales</span>
                    </div>
                    <div class="pd-card-body pd-space-y-6">
                        <div class="pd-check-group-inline">
                            <label><input type="checkbox" class="pd-checkbox"<?php echo $chk('rx_intra_periapicales'); ?>> Periapicales</label>
                            <label><input type="checkbox" class="pd-checkbox"<?php echo $chk('rx_intra_seriada'); ?>> Seriada</label>
                        </div>

                        <div class="pd-flex-col pd-items-center pd-scroll" style="gap:1.5rem; background:#f0f3f9; padding:1.25rem; border-radius:0.75rem; border:1px solid rgba(59,130,246,0.05); width:100%; overflow-x:auto;">
                            <div class="pd-odonto">
                                <div class="pd-odonto-label pd-odonto-label-sm">D</div>
                                <div class="pd-odonto-rows">
                                    <?php $render_rx_rows($rx_left_rows); ?>
                                </div>
                                <div class="pd-odonto-divider" style="height:3.5rem;"></div>
                                <div class="pd-odonto-rows">
                                    <?php $render_rx_rows($rx_right_rows); ?>
                                </div>
                                <div class="pd-odonto-label pd-odonto-label-sm">I</div>
                            </div>

                            <div class="pd-odonto-infantil">
                                <div class="pd-odonto-label pd-odonto-label-sm" style="opacity:0.9;">D</div>
                                <div class="pd-odonto-rows" style="width:15rem; flex-shrink:0;">
                                    <div class="pd-odonto-row" style="justify-content:flex-end;">
                                        <?php
                                        foreach (['55', '54', '53', '52', '51'] as $n) {
                                            $f = 'rxintra_diente_' . $n;
                                            $lbl = isset($data[$f]) ? esc_html((string) $data[$f]) : esc_html($n);
                                            echo '<label class="pd-tooth"><input type="checkbox"' . $tooth($f) . '><div class="pd-tooth-box pd-tooth-box-sm">' . $lbl . '</div></label>';
                                        }
                                        ?>
                                    </div>
                                    <div class="pd-odonto-row" style="justify-content:flex-end;">
                                        <?php
                                        foreach (['85', '84', '83', '82', '81'] as $n) {
                                            $f = 'rxintra_diente_' . $n;
                                            $lbl = isset($data[$f]) ? esc_html((string) $data[$f]) : esc_html($n);
                                            echo '<label class="pd-tooth"><input type="checkbox"' . $tooth($f) . '><div class="pd-tooth-box pd-tooth-box-sm">' . $lbl . '</div></label>';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="pd-odonto-divider" style="height:3.5rem;"></div>
                                <div class="pd-odonto-rows" style="width:15rem; flex-shrink:0;">
                                    <div class="pd-odonto-row">
                                        <?php
                                        foreach (['61', '62', '63', '64', '65'] as $n) {
                                            $f = 'rxintra_diente_' . $n;
                                            $lbl = isset($data[$f]) ? esc_html((string) $data[$f]) : esc_html($n);
                                            echo '<label class="pd-tooth"><input type="checkbox"' . $tooth($f) . '><div class="pd-tooth-box pd-tooth-box-sm">' . $lbl . '</div></label>';
                                        }
                                        ?>
                                    </div>
                                    <div class="pd-odonto-row">
                                        <?php
                                        foreach (['71', '72', '73', '74', '75'] as $n) {
                                            $f = 'rxintra_diente_' . $n;
                                            $lbl = isset($data[$f]) ? esc_html((string) $data[$f]) : esc_html($n);
                                            echo '<label class="pd-tooth"><input type="checkbox"' . $tooth($f) . '><div class="pd-tooth-box pd-tooth-box-sm">' . $lbl . '</div></label>';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="pd-odonto-label pd-odonto-label-sm" style="opacity:0.9;">I</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pd-col-der">

                <div class="pd-card" style="flex:1;">
                    <div class="pd-card-header">
                        <span>Ortodoncia</span>
                    </div>
                    <div class="pd-card-body pd-space-y-6">
                        <div class="pd-subsection">
                            <h4 class="pd-subsection-title">
                                <span class="pd-subsection-bar"></span> Análisis en Rx Lateral
                            </h4>
                            <div class="pd-check-grid">
                                <?php
                                $orto_lat = [
                                    'orto_lat_ricketts' => 'Ricketts',
                                    'orto_lat_steiner' => 'Steiner',
                                    'orto_lat_tweed' => 'Tweed',
                                    'orto_lat_downs' => 'Downs',
                                    'orto_lat_roth_jarabak' => 'Roth-Jarabak',
                                    'orto_lat_adenoides' => 'Adenoides',
                                    'orto_lat_vto_crecimiento' => 'Vto. Crecimiento',
                                    'orto_lat_rocabado' => 'Rocabado',
                                    'orto_lat_burstone_legan' => 'Burstone-Legan',
                                    'orto_lat_upc' => 'U.P.C.',
                                    'orto_lat_rampal' => 'Rampal',
                                    'orto_lat_face' => 'FACE',
                                    'orto_lat_mcnamara' => 'Mc Namara',
                                    'orto_lat_upch' => 'U.P.C.H.',
                                    'orto_lat_bjork_jarabak' => 'Bjork-Jarabak',
                                    'orto_lat_trujillo' => 'TRUJILLO',
                                ];
                                foreach ($orto_lat as $fname => $label) {
                                    echo '<label><input type="checkbox" class="pd-checkbox"' . $chk($fname) . '> ' . esc_html($label) . '</label>';
                                }
                                ?>
                            </div>
                        </div>

                        <div class="pd-subsection-grid">
                            <div class="pd-subsection">
                                <h4 class="pd-subsection-title">
                                    <span class="pd-subsection-bar"></span> Rx Frontal
                                </h4>
                                <label style="display:flex; align-items:center; gap:0.5rem;">
                                    <input type="checkbox" class="pd-checkbox"<?php echo $chk('orto_front_ricketts'); ?>> Ricketts
                                </label>
                            </div>
                            <div class="pd-subsection">
                                <h4 class="pd-subsection-title">
                                    <span class="pd-subsection-bar"></span> Rx Carpal
                                </h4>
                                <div class="pd-space-y-3">
                                    <label style="display:flex; align-items:center; gap:0.5rem;">
                                        <input type="checkbox" class="pd-checkbox"<?php echo $chk('orto_carpal_fishman'); ?>> Fishman
                                    </label>
                                    <label style="display:flex; align-items:center; gap:0.5rem;">
                                        <input type="checkbox" class="pd-checkbox"<?php echo $chk('orto_carpal_tw2'); ?>> TW-2
                                    </label>
                                </div>
                            </div>
                            <div class="pd-subsection">
                                <h4 class="pd-subsection-title">
                                    <span class="pd-subsection-bar"></span> Fotografías
                                </h4>
                                <div class="pd-space-y-3">
                                    <label style="display:flex; align-items:center; gap:0.5rem;">
                                        <input type="checkbox" class="pd-checkbox"<?php echo $chk('orto_foto_extraoral'); ?>> Extraoral
                                    </label>
                                    <label style="display:flex; align-items:center; gap:0.5rem;">
                                        <input type="checkbox" class="pd-checkbox"<?php echo $chk('orto_foto_intraoral'); ?>> Intraoral
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pd-card">
                    <div class="pd-card-header">
                        <span>Protocolos de Ortodoncia</span>
                    </div>
                    <div class="pd-card-body pd-space-y-6">
                        <div class="pd-protocol-grid">
                            <div class="pd-protocol-card">
                                <label><input type="checkbox" class="pd-checkbox pd-checkbox-lg"<?php echo $chk('prot_paquete_a'); ?>> Paquete A</label>
                                <div class="pd-protocol-desc">RX panorámica, Rx lateral, estudio cefalométrico, fotos extraorales</div>
                            </div>
                            <div class="pd-protocol-card">
                                <label><input type="checkbox" class="pd-checkbox pd-checkbox-lg"<?php echo $chk('prot_paquete_b'); ?>> Paquete B</label>
                                <div class="pd-protocol-desc">RX panorámica, Rx lateral, estudios cefalométricos, fotos extra e intraoral</div>
                            </div>
                            <div class="pd-protocol-card">
                                <label><input type="checkbox" class="pd-checkbox pd-checkbox-lg"<?php echo $chk('prot_paquete_c'); ?>> Paquete C</label>
                                <div class="pd-protocol-desc">RX lateral, estudios cefalométricos, fotos extraorales</div>
                            </div>
                        </div>
                        <div class="pd-field-combo pd-pt-2">
                            <span class="pd-label pd-whitespace-nowrap">Especificaciones:</span>
                            <textarea rows="2" class="pd-textarea" readonly><?php echo $area('prot_especificaciones'); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="pd-card">
                    <div class="pd-card-header">
                        <span>Protocolo de guías quirúrgicas</span>
                    </div>
                    <div class="pd-card-body pd-space-y-4">
                        <label style="display:flex; align-items:center; gap:0.75rem; font-weight:700; color:#1e293b;">
                            <input type="checkbox" class="pd-checkbox pd-checkbox-lg"<?php echo $chk('guia_planificacion_implantes'); ?>> Planificación para implantes
                        </label>
                        <div class="pd-info-box">
                            Planificación de guías quirúrgica para implantes A (Cbct s/informe, escaneo intraoral, planificación e impresión de guía)
                        </div>
                        <label style="display:flex; align-items:center; gap:0.75rem; font-weight:600; color:#1e293b; margin-top:1rem;">
                            <input type="checkbox" class="pd-checkbox pd-checkbox-lg"<?php echo $chk('guia_escaneo_intraoral'); ?>> Escaneo Intraoral
                        </label>
                    </div>
                </div>

            </div>
        </div>

        <div class="pd-submit-area" style="display:none;" aria-hidden="true"></div>

    </div>
</section>
    <?php
}
