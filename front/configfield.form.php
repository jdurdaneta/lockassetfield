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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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

include('../../../inc/includes.php');

// Comprobación de derechos: solo usuarios con permiso UPDATE sobre
// el derecho principal del plugin pueden modificar la configuración de campos.
Session::checkRight(Config::$rightname, UPDATE);

// -------------------------------------------------------------------------
// 1) Gestión de alta/baja de itemtypes de GenericObject (acción "add")
// -------------------------------------------------------------------------
if (isset($_POST['add'])) {

    global $DB;

    $configfield = new ConfigField();

    // Recorremos todos los datos POST enviados desde la matriz de checkboxes
    foreach ($_POST as $itemtype => $check_value) {

        // Ignorar campos auxiliares de formulario que no son itemtypes
        if ($itemtype === 'add' || $itemtype === '_glpi_csrf_token') {
            continue;
        }

        // Instanciamos la clase del itemtype para obtener su etiqueta
        $object = new $itemtype();

        if ($check_value['is_exist'] === "1") {
            // Si el tipo de GenericObject está marcado y aún no existe en ConfigField,
            // lo insertamos en la tabla de configuración.
            if (!ConfigField::existInConfigField($itemtype)) {
                $configfield->add(['itemtype' => $itemtype]);
                Session::addMessageAfterRedirect(
                    __('<strong>' . __($object->getTypeName(1)) . '</strong> - Se ha añadido a Bloqueo de Campos', 'lockassetfield'),
                    false,
                    INFO,
                );
            }
        } else {
            // Si está desmarcado y existe en ConfigField, lo eliminamos de la configuración.
            if ($configfield->getFromDBByCrit(['itemtype' => $itemtype])) {
                $configfield->delete(['id' => $configfield->fields['id']]);
                Session::addMessageAfterRedirect(
                    __('<strong>' . __($object->getTypeName(1)) . '</strong> - Se ha eliminado de Bloqueo de Campos', 'lockassetfield'),
                    false,
                    WARNING
                );
            }
        }
    }

    // Volver a la página anterior (pestaña de configuración) tras procesar la acción.
    Html::back();

// -------------------------------------------------------------------------
// 2) Actualización de flags de bloqueo de campos (acción "update")
// -------------------------------------------------------------------------
} elseif (isset($_POST['update'])) {
    global $DB;

    // Cada entrada del POST representa un itemtype con sus flags de campos bloqueados
    foreach ($_POST as $fielditemtype => $fields) {
        // Ignorar campos que no son configuraciones (botones, token, etc.)
        if (!is_array($fields) || $fielditemtype === 'update' || $fielditemtype === '_glpi_csrf_token') {
            continue;
        }

        $configfield = new ConfigField();
        // Obtenemos el registro de configuración para ese itemtype
        $configfield->getFromDBByCrit(['itemtype' => $fielditemtype]);

        // Obtenemos el objeto del itemtype para construir mensajes amigables
        $itemtype = new $fielditemtype();

        // Añadimos el id del registro a actualizar al array de campos
        $fields['id'] = $configfield->fields['id'];

        // Intentamos guardar la actualización en la base de datos
        if (!$configfield->update($fields)) {

            Session::addMessageAfterRedirect(
                __('<strong>' . __($itemtype->getTypeName(1)) . '</strong> - Error en la actualización', 'lockassetfield'),
                false,
                ERROR
            );
        } else {
            // Solo mostramos mensaje de éxito si realmente hubo cambios registrados
            if (count($configfield->updates) > 0) {
                Session::addMessageAfterRedirect(
                    __('<strong>' . __($itemtype->getTypeName(1)) . '</strong> - Campos actualizados', 'lockassetfield'),
                    false,
                    INFO
                );
            }
        }
    }

    // Volvemos a la página anterior tras procesar la actualización de campos
    Html::back();

// -------------------------------------------------------------------------
// 3) Actualización de estados bloqueados para el campo states_id (acción "update_states")
// -------------------------------------------------------------------------
} elseif (isset($_POST['update_states'])) {
     global $DB;
    
    $configfield = new ConfigField();

    // Cada entrada del POST incluye datos para un determinado itemtype
    foreach ($_POST as $fieldSatesTypes => $fields) {
        $form_data = [];

        // Ignorar campos que no son configuraciones
        if (!is_array($fields) || $fieldSatesTypes === 'update' || $fieldSatesTypes === '_glpi_csrf_token') {
            continue;
        }
        
        // Construimos la estructura de datos a actualizar:
        //  - id: el registro de configuración existente.
        //  - state_ids: lista de estados (JSON) o null si no se seleccionó nada.
        $form_data = [
            'id'        => $fields['id'],
            'state_ids' => isset($fields['state_ids']) ? json_encode(array_map('intval', $fields['state_ids'])) : NULL
        ];
        
        // Obtenemos el objeto del itemtype para mensajes de feedback
        $itemtype = new $fieldSatesTypes();
        
        // Actualizamos los estados bloqueados para el itemtype
        if (!$configfield->update($form_data)) {           
            
            Session::addMessageAfterRedirect(
                __('<strong>' . _-($itemtype->getTypeName(1)) . '</strong> - Error en la actualización', 'lockassetfield'),
                false,
                ERROR
            );
        } else {
            if (count($configfield->updates) > 0) {
                Session::addMessageAfterRedirect(
                    __('<strong>' . __($itemtype->getTypeName(1)) . '</strong> - Campos actualizados', 'lockassetfield'),
                    true,
                    INFO
                );
            }
        }
    }

    // Volver a la página anterior tras procesar la actualización de estados
    Html::back();

// -------------------------------------------------------------------------
// 4) Caso por defecto: acceso sin acción válida → redirección
// -------------------------------------------------------------------------
} else {

    // Si no se recibe ninguna acción esperada, redirigimos a la página de configuración principal.
    Html::redirect($CFG_GLPI['root_doc'] . '/plugins/lockassetfield/front/config.php');

    // Llamada a footer (aunque tras redirect normalmente no se ejecuta).
    Html::footer();
}
