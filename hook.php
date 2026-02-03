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
use GlpiPlugin\Lockassetfield\Profile;
use GlpiPlugin\Lockassetfield\ConfigField;

/**
 * Proceso de instalación del plugin.
 *
 * Esta función es llamada por GLPI cuando se instala el plugin desde
 * la interfaz de administración. Sus responsabilidades incluyen:
 *  - Verificar que la versión de GLPI sea compatible.
 *  - Crear las tablas de configuración necesarias en la base de datos.
 *
 * @return bool True si la instalación se ha realizado correctamente, false en caso de error.
 */
function plugin_lockassetfield_install(): bool
{
    global $DB;

    // Verificar la versión de GLPI instalada frente a la versión mínima requerida.
    $glpiVersion = GLPI_VERSION;
    if (version_compare($glpiVersion, '10.0.0', '<')) {
        Session::addMessageAfterRedirect(
            __('This plugin requires GLPI >= 10.0.0', 'lockassetfield'),
            false,
            ERROR
        );
        return false;
    }

    // Creación de la tabla glpi_plugin_lockassetfield_configs en BBDD.
    Config::install();

    // Creación de la tabla glpi_plugin_lockassetfield_configfields en BBDD.
    ConfigField::install();

    return true;
}

/**
 * Proceso de desinstalación del plugin.
 *
 * Esta función es llamada por GLPI cuando se desinstala el plugin desde
 * la interfaz de administración. Sus responsabilidades incluyen:
 *  - Eliminar las tablas propias del plugin.
 *  - Limpiar la configuración y derechos asociados en perfiles.
 *  - Eliminar registros relacionados del histórico de logs.
 *
 * @return bool True si la desinstalación se ha realizado correctamente, false en caso de error.
 */
function plugin_lockassetfield_uninstall()
{
    global $DB;

    // Tablas propias del plugin que se eliminarán de la base de datos.
    $tables = [
        'glpi_plugin_lockassetfield_configs',
        'glpi_plugin_lockassetfield_configfields',
    ];

    foreach ($tables as $table) {
        $query = "DROP TABLE IF EXISTS `{$table}`";
        $DB->query($query) or die($DB->error());
    }

    // Elimina la configuración y derechos definidos en Profile.
    Profile::uninstall();

    // Quita los derechos del perfil actual registrados para este plugin.
    foreach (Profile::getAllRights() as $right) {
        ProfileRight::deleteProfileRights([$right['field']]);
    }

    $itemtype = addslashes(Config::class);

    // Tablas para eliminar registros relacionados.
    $relatedTables = [
        'glpi_logs'
    ];

    // Eliminar registros relacionados con el tipo de item del plugin (limpieza de históricos).
    foreach ($relatedTables as $table) {
        $query = "DELETE FROM `$table` WHERE `itemtype` = '$itemtype'";
        $DB->queryOrDie($query, $DB->error());
    }

    return true;
}

/**
 * Hook pre_item_update: bloquea la edición de campos configurados.
 *
 * Este hook se ejecuta antes de que GLPI actualice un elemento
 * (ordenadores, monitores, etc.) cuyo tipo esté soportado por el plugin.
 *
 * Comportamiento:
 *  - Si el usuario tiene permisos de configuración (super-admin o derecho específico del plugin),
 *    no se aplica ningún bloqueo.
 *  - Para el resto de usuarios, se consultan los campos bloqueados para el tipo de item
 *    (incluyendo los bloqueados por estado) y se impide su modificación:
 *      - Se restauran los valores originales desde la base de datos.
 *      - Se muestra un mensaje de advertencia al usuario.
 *
 * @param CommonDBTM $item Instancia del elemento que se está actualizando.
 *
 * @return bool True para permitir que GLPI continúe el flujo de actualización.
 */
function plugin_lockassetfield_pre_item_update($item)
{
    // Verificar si el usuario tiene permisos para editar (super-admin o derecho específico del plugin).
    if (Session::haveRight('config', UPDATE) || Session::haveRight(Config::$rightname, Config::RIGHT_UPDATE_FIELDS)) {
        return true;
    }

    $input = &$item->input;

    // Si no hay ID en el input, no se puede verificar el estado previo: no se aplica bloqueo.
    if (!isset($input['id'])) {
        return true;
    }

    $itemtype = get_class($item);

    // Obtenemos los campos bloqueados por tipo de activo.
    $locked_fields = ConfigField::getLockedFieldsForItemType($itemtype);

    // Obtenemos los campos bloqueados condicionados por el estado actual (states_id).
    $locked_field_state = ConfigField::isFieldStateLocked($itemtype, $item->fields['states_id']);
    if ($locked_field_state) {
        // Unificamos todos los campos a bloquear (configuración general + por estado).
        $locked_fields = array_merge($locked_fields, $locked_field_state);
    }

    // Si no hay campos configurados como bloqueados, no es necesario hacer nada.
    if (empty($locked_fields)) {
        return true;
    }

    // Obtener los valores actuales del registro en base de datos para comparar y restaurar si es necesario.
    $current = new $itemtype();
    if ($current->getFromDB($input['id'])) {
        $changed = false;

        foreach ($locked_fields as $field) {
            if (
                isset($input[$field]) &&
                $input[$field] != $current->fields[$field]
            ) {
                // Restaurar el valor original del campo bloqueado.
                $input[$field] = $current->fields[$field];
                $changed = true;

                // Etiqueta amigable del campo para mostrar en el mensaje de advertencia.
                $field_label = ConfigField::getLockfieldFieldLabel($field);
                Session::addMessageAfterRedirect(
                    sprintf(
                        __('El campo "%s" no puede ser modificado', 'lockassetfield'),
                        $field_label
                    ),
                    false,
                    WARNING
                );
            }
        }

        // Si se han modificado valores de entrada, actualizar el input del item.
        if ($changed) {
            $item->input = $input;
        }
    }

    return true;
}

/**
 * Hook post_show_item: añade JavaScript para hacer campos de solo lectura.
 *
 * Este hook se ejecuta después de mostrar el formulario de edición de un item
 * en la interfaz de GLPI. Su objetivo es:
 *  - Localizar los campos que han sido configurados como bloqueados para
 *    el tipo de item actual (y su estado).
 *  - Inyectar código JavaScript que:
 *      - Marca los campos como readonly/disabled.
 *      - Aplica estilos visuales para mostrar que están bloqueados.
 *      - Añade un tooltip explicativo.
 *
 * Nota: los usuarios con derechos de configuración o con el permiso específico
 * del plugin quedan excluidos de este bloqueo visual.
 *
 * @param array $params Parámetros proporcionados por el hook, incluyendo:
 *                      - 'item' => instancia del objeto mostrado (CommonDBTM).
 *
 * @return bool True para indicar que el hook se ha ejecutado correctamente.
 */
function plugin_lockassetfield_post_show_item($params)
{

    $item = $params['item'];
    $itemtype = $item->getType();

    if (!ConfigField::existInConfigField($itemtype)) {
        return true;
    }

    // Verificar si el usuario tiene permisos para editar (super-admin o derecho específico del plugin).
    if (Session::haveRight('config', UPDATE) || Session::haveRight(Config::$rightname, Config::RIGHT_UPDATE_FIELDS)) {
        return true;
    }


    // Obtenemos los campos bloqueados por tipo de activo.
    $locked_fields = ConfigField::getLockedFieldsForItemType($itemtype);

    // Obtenemos los campos bloqueados según el estado actual (states_id).
    $locked_field_state = ConfigField::isFieldStateLocked($itemtype, $item->fields['states_id']);
    if ($locked_field_state) {
        // Unificamos todos los campos a bloquear (configuración general + por estado).
        $locked_fields = array_merge($locked_fields, $locked_field_state);
    }

    // Si no hay campos bloqueados, no se inyecta ningún JavaScript adicional.
    if (empty($locked_fields)) {
        return true;
    }

    echo "<script type='text/javascript'>
        $(document).ready(function() { ";
    // Para cada campo bloqueado se aplican propiedades de solo lectura y estilos visuales.
    foreach ($locked_fields as $field) {
        $tooltip = __('Esta campo esta bloqueado por configuración', 'lockassetfield');
        echo "
            // Lock field: {$field}
            $('input[name=\"{$field}\"], select[name=\"{$field}\"], textarea[name=\"{$field}\"]')
                .prop('readonly', true)
                .prop('disabled', true)
                .css({
                    'background-color': '#f5f5f5',
                    'cursor': 'not-allowed',
                    'opacity': '0.6'
                })
                .attr('title', '{$tooltip}');
            ";
    }

    echo "
        });
        </script>";
    return true;
}
