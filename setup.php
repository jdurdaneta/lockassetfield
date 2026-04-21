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

// Versión del plugin y rango de versiones de GLPI soportadas.
define('PLUGIN_LOCKASSETFIELD_VERSION', '1.0.0');
define('PLUGIN_LOCKASSETFIELD_MIN_GLPI', '10.0.0');
define('PLUGIN_LOCKASSETFIELD_MAX_GLPI', '11.0.99');

use GlpiPlugin\Lockassetfield\Config;
use GlpiPlugin\Lockassetfield\Profile;
use GlpiPlugin\Lockassetfield\ConfigField;

/**
 * Registra los hooks del plugin en GLPI.
 *
 * Esta función es llamada automáticamente por GLPI en la fase de
 * inicialización de plugins. Aquí se declaran:
 *  - Compatibilidad CSRF.
 *  - Registro de clases de plugin (perfiles, configuración).
 *  - Menú de configuración.
 *  - Hooks de actualización de elementos e inyección en formularios.
 *
 * REQUIRED por la API de plugins de GLPI.
 *
 * @return void
 */
function plugin_init_lockassetfield()
{
    global $PLUGIN_HOOKS;

    // Declarar que el plugin cumple con el sistema de protección CSRF de GLPI.
    $PLUGIN_HOOKS['csrf_compliant']['lockassetfield'] = true;

    $plugin = new Plugin();
    if ($plugin->isInstalled('lockassetfield') && $plugin->isActivated('lockassetfield')) {

        // Registrar la clase Profile del plugin para añadir pestañas en la ficha de perfiles.
        \Plugin::registerClass(Profile::class, [
            'addtabon' => ['Profile'],
        ]);

        // Menú de configuración: accesible desde la sección de configuración de GLPI
        // según los derechos del usuario.
        if (Session::haveRight('config', UPDATE) || Session::haveRight(Config::$rightname, READ)) {
            // Página principal de configuración del plugin.
            $PLUGIN_HOOKS['config_page']['lockassetfield'] = 'front/config.php';

            // Entrada en el menú de configuración, asociada a la clase Config del plugin.
            $PLUGIN_HOOKS['menu_toadd']['lockassetfield']
                = ['config' => Config::class];
        }

        // Solo registrar hooks de bloqueo de campos si la funcionalidad está activa en la configuración.
        if (Config::isLockAssetFieldActive()) {

            // Obtenemos los tipos de activos soportados desde la configuración.
            // Cada tipo de activo tendrá asociado el mismo callback de pre-actualización.
            $asset_types = ConfigField::getSupportedAssetTypes();
            
            // Hook para interceptar ANTES de actualizar items (pre_item_update).
            // Construye un array con clave = tipo de activo y valor = función callback.
            $preItemUpdate = array_fill_keys($asset_types, 'plugin_lockassetfield_pre_item_update');
            $PLUGIN_HOOKS['pre_item_update']['lockassetfield'] = $preItemUpdate;
            
            // Hook para modificar el formulario y hacer los campos de solo lectura
            // en la interfaz de edición de los activos soportados.
            $PLUGIN_HOOKS['post_show_item']['lockassetfield'] = 'plugin_lockassetfield_post_show_item';
        }
    }
}

/**
 * Devuelve la información básica del plugin para GLPI.
 *
 * Esta función informa a GLPI sobre:
 *  - Nombre visible del plugin.
 *  - Versión actual.
 *  - Autor y licencia.
 *  - URL de la página del plugin.
 *  - Requisitos de versión de GLPI (mínima y máxima).
 *
 * REQUIRED por la API de plugins de GLPI.
 *
 * @return array<string,mixed> Datos de metainformación del plugin.
 */
function plugin_version_lockassetfield()
{
    return [
        'name'           => 'Bloqueo de campos de activos',
        'version'        => PLUGIN_LOCKASSETFIELD_VERSION,
        'author'         => 'Equipo INTEF',
        'license'        => 'GPLv2+',
        'homepage'       => 'https://github.com/jdurdaneta/glpi-lockassetfield',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_LOCKASSETFIELD_MIN_GLPI,
                'max' => PLUGIN_LOCKASSETFIELD_MAX_GLPI,
            ]
        ]
    ];
}

/**
 * Comprueba los prerrequisitos antes de instalar/activar el plugin.
 *
 * Verifica que la versión de GLPI en ejecución se encuentre
 * dentro del rango soportado por el plugin:
 *  - GLPI_VERSION >= PLUGIN_LOCKASSETFIELD_MIN_GLPI
 *  - GLPI_VERSION <= PLUGIN_LOCKASSETFIELD_MAX_GLPI
 *
 * OPTIONNAL pero recomendado por la API de plugins.
 *
 * @return bool True si los prerrequisitos se cumplen, false en caso contrario.
 */
function plugin_lockassetfield_check_prerequisites()
{
    if (
        version_compare(GLPI_VERSION, PLUGIN_LOCKASSETFIELD_MIN_GLPI, 'lt')
        || version_compare(GLPI_VERSION, PLUGIN_LOCKASSETFIELD_MAX_GLPI, 'gt')
    ) {
        echo sprintf(
            'This plugin requires GLPI >= %s and < %s',
            PLUGIN_LOCKASSETFIELD_MIN_GLPI,
            PLUGIN_LOCKASSETFIELD_MAX_GLPI
        );
        return false;
    }
    return true;
}

/**
 * Comprueba si el plugin está correctamente configurado.
 *
 * Esta función se utiliza principalmente para que GLPI pueda mostrar,
 * en la interfaz de gestión de plugins, si el plugin está:
 *  - Instalado y configurado.
 *  - Instalado pero pendiente de configuración.
 *
 * En este ejemplo siempre devuelve true, pero se puede extender para
 * realizar verificaciones reales (p.ej. valores obligatorios en la tabla
 * de configuración del plugin).
 *
 * @param bool $verbose Indica si se debe mostrar un mensaje en caso de fallo.
 *                      Por defecto false (no mostrar nada).
 *
 * @return bool True si la configuración es válida, false en caso contrario.
 */
function plugin_lockassetfield_check_config($verbose = false)
{
    if (true) { // Your configuration check
        return true;
    }

    if ($verbose) {
        echo __('Installed / not configured', 'lockassetfield');
    }
    return false;
}
