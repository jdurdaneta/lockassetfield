<?php

/**
 * -------------------------------------------------------------------------
 * LockAssetField plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of LockAssetField.
 *
 * LockAssetField is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * LockAssetField is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with LockAssetField. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2025 by LockAssetField plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/lockassetfield
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Lockassetfield;

use CommonDBTM;
use Glpi\Asset\AssetDefinition;
use Html;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Clase ConfigAssetObject.
 *
 * Gestiona la integración del plugin LockAssetField con los activos
 * personalizados de GLPI 11.
 *
 * En GLPI 10 esta funcionalidad dependía del plugin GenericObject.
 * En GLPI 11 los activos personalizados se gestionan desde el core
 * mediante Asset Definitions.
 *
 * Responsabilidades:
 *  - Obtener los activos personalizados activos definidos en GLPI.
 *  - Mostrar la pantalla "Gestión de objetos".
 *  - Permitir activar/desactivar qué activos personalizados estarán
 *    disponibles para el bloqueo de campos.
 *  - Guardar la selección en la tabla de configuración de campos.
 */
class ConfigAssetObject extends CommonDBTM
{
    /**
     * Derecho principal asociado a este objeto.
     *
     * @var string
     */
    public static $rightname = 'plugin_lockassetfield_config';

    /**
     * Devuelve el nombre localizado del tipo actual.
     *
     * @param int $nb Número de elementos.
     *
     * @return string
     */
    public static function getTypeName($nb = 0): string
    {
        return __('Gestión de objetos', 'lockassetfield');
    }

    /**
     * Devuelve el nombre del menú.
     *
     * @return string
     */
    public static function getMenuName(): string
    {
        return self::getTypeName();
    }

    /**
     * Devuelve todas las definiciones de activos personalizados activas.
     *
     * Cada elemento contiene:
     * - id
     * - name
     * - label
     * - system_name
     * - itemtype
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getCustomAssetDefinitions(): array
    {
        $definitions = [];

        if (!class_exists(AssetDefinition::class)) {
            return $definitions;
        }

        $assetDefinition = new AssetDefinition();

        foreach ($assetDefinition->find(['is_active' => 1], ['id ASC']) as $row) {
            $definition = new AssetDefinition();

            if (!$definition->getFromDB((int) $row['id'])) {
                continue;
            }

            if (!method_exists($definition, 'getAssetClassName')) {
                continue;
            }

            $itemtype = $definition->getAssetClassName();

            if (!class_exists($itemtype)) {
                continue;
            }

            $definitions[] = [
                'id'          => (int) $definition->fields['id'],
                'name'        => (string) ($definition->fields['name'] ?? ''),
                'label'       => self::getDefinitionLabel($definition),
                'system_name' => (string) ($definition->fields['system_name'] ?? ''),
                'itemtype'    => $itemtype,
            ];
        }

        return $definitions;
    }

    /**
     * Devuelve solo los itemtypes de activos personalizados activos.
     *
     * @return array<int, string>
     */
    public static function getAvailableCustomAssetItemtypes(): array
    {
        return array_values(
            array_map(
                static fn(array $definition): string => $definition['itemtype'],
                self::getCustomAssetDefinitions()
            )
        );
    }

    /**
     * Devuelve etiquetas de activos personalizados indexadas por itemtype.
     *
     * @return array<string, string>
     */
    public static function getCustomAssetLabels(): array
    {
        $labels = [];

        foreach (self::getCustomAssetDefinitions() as $definition) {
            $labels[$definition['itemtype']] = $definition['label'];
        }

        return $labels;
    }

    /**
     * Indica si un itemtype pertenece a un activo personalizado activo.
     *
     * @param string $itemtype Itemtype a comprobar.
     *
     * @return bool
     */
    public static function isCustomAssetItemtype(string $itemtype): bool
    {
        return in_array($itemtype, self::getAvailableCustomAssetItemtypes(), true);
    }

    /**
     * Devuelve la matriz usada para mostrar la selección de activos personalizados.
     *
     * @return array<string, mixed>
     */
    public static function getMatrixAssetFields(): array
    {
        $matrix = [
            'rows' => [],
        ];

        foreach (self::getCustomAssetDefinitions() as $definition) {
            $itemtype = $definition['itemtype'];

            $matrix['rows'][$itemtype] = [
                'label'   => $definition['label'],
                'checked' => ConfigField::existInConfigField($itemtype),
            ];
        }

        return $matrix;
    }

    /**
     * Muestra el formulario de selección de activos personalizados.
     *
     * @return bool
     */
    public static function showConfigFieldForm(): bool
    {
        global $CFG_GLPI;

        $matrix = self::getMatrixAssetFields();

        echo '<table class="tab_cadre_fixe"><tbody>';
        echo '<tr><th>' . __('Añadir activos personalizados', 'lockassetfield') . '</th></tr>';
        echo '<tr><td>' . __('Seleccione los activos personalizados que desea hacer disponibles para el bloqueo de campos.', 'lockassetfield') . '</td></tr>';
        echo '</tbody></table>';

        echo '<div class="card">';
        echo '<div class="spaced p-3">';
        echo '<div class="field-container">';
        echo '<form method="post" action="' . $CFG_GLPI['root_doc'] . '/plugins/lockassetfield/front/configfield.form.php">';
        Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

        echo '<table class="tab_cadre_fixe">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Modelos', 'lockassetfield') . '</th>';
        echo '<th class="center">' . __('Activo', 'lockassetfield') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        if (empty($matrix['rows'])) {
            echo '<tr>';
            echo '<td colspan="2" class="center">';
            echo __('No hay activos personalizados activos definidos en GLPI.', 'lockassetfield');
            echo '</td>';
            echo '</tr>';
        }

        foreach ($matrix['rows'] as $itemtype => $row) {
            $checkboxId = 'lockassetfield_asset_object_' . md5($itemtype);

            echo '<tr>';
            echo '<td>';
            echo htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8');
            echo '<br><small class="text-muted">';
            echo htmlspecialchars($itemtype, ENT_QUOTES, 'UTF-8');
            echo '</small>';
            echo '</td>';

            echo '<td class="center">';
            echo '<input type="checkbox"'
                . ' id="' . $checkboxId . '"'
                . ' name="custom_asset_itemtypes[]"'
                . ' value="' . htmlspecialchars($itemtype, ENT_QUOTES, 'UTF-8') . '"'
                . ($row['checked'] ? ' checked' : '')
                . (!self::canUpdate() ? ' disabled' : '')
                . '>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        echo '<div class="d-flex justify-content-center gap-3 mt-3">';
        echo '<div class="w-auto">';

        if (self::canUpdate()) {
            echo '<button type="submit" name="update_asset_objects" value="1" class="ms-auto btn btn-primary">';
            echo '<i class="fas fa-save me-2"></i>' . __('Save');
            echo '</button>';
        }

        echo '</div>';
        echo '</div>';

        Html::closeForm();

        echo '</div>';
        echo '</div>';
        echo '</div>';

        return true;
    }

    /**
     * Guarda la selección de activos personalizados.
     *
     * @param array<int, string> $selectedItemtypes Itemtypes seleccionados.
     *
     * @return array<string, int>
     */
    public static function saveSelection(array $selectedItemtypes): array
    {
        global $DB;

        $table = getTableForItemType(ConfigField::class);

        $availableItemtypes = self::getAvailableCustomAssetItemtypes();

        $selectedItemtypes = array_values(
            array_intersect(
                array_unique($selectedItemtypes),
                $availableItemtypes
            )
        );

        $added = 0;
        $deleted = 0;

        foreach ($availableItemtypes as $itemtype) {
            $exists = countElementsInTable($table, ['itemtype' => $itemtype]) > 0;
            $shouldExist = in_array($itemtype, $selectedItemtypes, true);

            if ($shouldExist && !$exists) {
                $inserted = $DB->insert(
                    $table,
                    [
                        'plugin_lockassetfield_configs_id' => 1,
                        'itemtype'                         => $itemtype,
                        'state_ids'                        => null,
                        'serial_locked'                    => 0,
                        'otherserial_locked'               => 0,
                        'manufacturers_id_locked'          => 0,
                        'models_id_locked'                 => 0,
                        'types_id_locked'                  => 0,
                        'date_creation'                    => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
                        'date_mod'                         => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
                    ]
                );

                if ($inserted) {
                    $added++;
                }

                continue;
            }

            if (!$shouldExist && $exists) {
                $DB->delete(
                    $table,
                    [
                        'itemtype' => $itemtype,
                    ]
                );

                $deleted++;
            }
        }

        return [
            'selected'  => count($selectedItemtypes),
            'available' => count($availableItemtypes),
            'added'     => $added,
            'deleted'   => $deleted,
        ];
    }

    /**
     * Procesa el POST del formulario de selección de activos personalizados.
     *
     * @param array $post Datos POST.
     *
     * @return void
     */
    public static function saveSelectionFromPost(array $post): void
    {
        Session::checkRight(Config::$rightname, UPDATE);

        $selectedItemtypes = $post['custom_asset_itemtypes'] ?? [];

        if (!is_array($selectedItemtypes)) {
            $selectedItemtypes = [];
        }

        $result = self::saveSelection($selectedItemtypes);

        if ($result['selected'] === 0) {
            Session::addMessageAfterRedirect(
                __('No se seleccionó ningún activo personalizado.', 'lockassetfield'),
                false,
                WARNING
            );

            return;
        }

        if ($result['added'] === 0 && $result['deleted'] === 0) {
            Session::addMessageAfterRedirect(
                __('No se realizaron cambios. Los activos seleccionados ya estaban en el estado indicado.', 'lockassetfield'),
                false,
                INFO
            );

            return;
        }

        Session::addMessageAfterRedirect(
            sprintf(
                __('Configuración guardada. Añadidos: %s. Eliminados: %s.', 'lockassetfield'),
                $result['added'],
                $result['deleted']
            ),
            false,
            INFO
        );
    }

    /**
     * Obtiene la etiqueta visible de una definición.
     *
     * @param AssetDefinition $definition Definición de activo.
     *
     * @return string
     */
    private static function getDefinitionLabel(AssetDefinition $definition): string
    {
        if (!empty($definition->fields['label'])) {
            return (string) $definition->fields['label'];
        }

        if (!empty($definition->fields['name'])) {
            return (string) $definition->fields['name'];
        }

        return $definition->getAssetClassName();
    }
}