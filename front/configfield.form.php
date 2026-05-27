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

use GlpiPlugin\Lockassetfield\Config;
use GlpiPlugin\Lockassetfield\ConfigField;
use GlpiPlugin\Lockassetfield\ConfigAssetObject;

include('../../../inc/includes.php');

global $CFG_GLPI;

// Solo usuarios con permiso UPDATE sobre el plugin pueden modificar la configuración.
Session::checkRight(Config::$rightname, UPDATE);

/**
 * Devuelve una etiqueta visible para un itemtype.
 *
 * - Para activos personalizados de GLPI 11 usa las etiquetas obtenidas desde
 *   las Asset Definitions.
 * - Para tipos estándar intenta usar getTypeName().
 * - Si no puede resolverlo, devuelve el itemtype tal cual.
 *
 * @param string $itemtype Nombre del itemtype.
 *
 * @return string
 */
function plugin_lockassetfield_get_itemtype_label(string $itemtype): string
{
    $customLabels = ConfigAssetObject::getCustomAssetLabels();

    if (isset($customLabels[$itemtype])) {
        return $customLabels[$itemtype];
    }

    if (class_exists($itemtype)) {
        $item = new $itemtype();

        return $item->getTypeName(1);
    }

    return $itemtype;
}

/**
 * Devuelve true si el itemtype forma parte de los soportados por el plugin.
 *
 * @param string $itemtype Nombre del itemtype.
 *
 * @return bool
 */
function plugin_lockassetfield_is_supported_itemtype(string $itemtype): bool
{
    return in_array($itemtype, ConfigField::getSupportedAssetTypes(), true);
}

// -------------------------------------------------------------------------
// 1) Actualización de flags de bloqueo de campos (acción "update")
// -------------------------------------------------------------------------
if (isset($_POST['update'])) {
    $matrixRowKeyMap = ConfigField::getMatrixRowKeyToItemtypeMap();

    foreach ($_POST as $fieldItemtype => $fields) {
        // Ignorar botones, token y cualquier elemento que no sea un array de configuración
        if (
            !is_array($fields)
            || $fieldItemtype === 'update'
            || $fieldItemtype === '_glpi_csrf_token'
        ) {
            continue;
        }

        // Si la matriz usó una clave segura para un Asset Definition,
        // la convertimos de nuevo al itemtype real antes de validar y guardar.
        if (isset($matrixRowKeyMap[$fieldItemtype])) {
            $fieldItemtype = $matrixRowKeyMap[$fieldItemtype];
        }

        if (!plugin_lockassetfield_is_supported_itemtype($fieldItemtype)) {
            continue;
        }

        $configfield = new ConfigField();

        if (!$configfield->getFromDBByCrit(['itemtype' => $fieldItemtype])) {
            continue;
        }

        $fields['id'] = $configfield->fields['id'];
        $itemtypeLabel = plugin_lockassetfield_get_itemtype_label($fieldItemtype);

        if (!$configfield->update($fields)) {
            Session::addMessageAfterRedirect(
                '<strong>' . $itemtypeLabel . '</strong> - ' . __('Error en la actualización', 'lockassetfield'),
                false,
                ERROR
            );
        } else {
            if (count($configfield->updates) > 0) {
                Session::addMessageAfterRedirect(
                    '<strong>' . $itemtypeLabel . '</strong> - ' . __('Campos actualizados', 'lockassetfield'),
                    false,
                    INFO
                );
            }
        }
    }

    Html::back();

// -------------------------------------------------------------------------
// 2) Actualización de estados bloqueados para el campo states_id
// -------------------------------------------------------------------------
} elseif (isset($_POST['update_states'])) {
    $configfield = new ConfigField();

    foreach ($_POST as $fieldStatesType => $fields) {
        if (
            !is_array($fields)
            || $fieldStatesType === 'update_states'
            || $fieldStatesType === '_glpi_csrf_token'
        ) {
            continue;
        }

        if (!plugin_lockassetfield_is_supported_itemtype($fieldStatesType)) {
            continue;
        }

        if (empty($fields['id'])) {
            continue;
        }

        $formData = [
            'id'        => $fields['id'],
            'state_ids' => isset($fields['state_ids'])
                ? json_encode(array_map('intval', $fields['state_ids']))
                : null,
        ];

        $itemtypeLabel = plugin_lockassetfield_get_itemtype_label($fieldStatesType);

        if (!$configfield->update($formData)) {
            Session::addMessageAfterRedirect(
                '<strong>' . $itemtypeLabel . '</strong> - ' . __('Error en la actualización', 'lockassetfield'),
                false,
                ERROR
            );
        } else {
            if (count($configfield->updates) > 0) {
                Session::addMessageAfterRedirect(
                    '<strong>' . $itemtypeLabel . '</strong> - ' . __('Campos actualizados', 'lockassetfield'),
                    true,
                    INFO
                );
            }
        }
    }

    Html::back();

// -------------------------------------------------------------------------
// 3) Actualización de activos personalizados disponibles para el bloqueo
// -------------------------------------------------------------------------
} elseif (isset($_POST['update_asset_objects'])) {
    ConfigAssetObject::saveSelectionFromPost($_POST);

    Html::back();

// -------------------------------------------------------------------------
// 4) Caso por defecto: acceso sin acción válida
// -------------------------------------------------------------------------
} else {
    Html::redirect($CFG_GLPI['root_doc'] . '/plugins/lockassetfield/front/config.php');
    Html::footer();
}