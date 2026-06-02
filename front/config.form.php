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

include('../../../inc/includes.php');

// Validación de derechos: solo usuarios con permiso UPDATE sobre el derecho
// declarado en Config::$rightname pueden ejecutar esta acción.
// Si el usuario no tiene permiso, GLPI finaliza la ejecución con error.
Session::checkRight(Config::$rightname, UPDATE);

// Si el formulario llega con el botón "update", procesamos la actualización.
if (isset($_POST['update'])) {
    // Se crea una instancia del objeto Config y se actualiza
    // usando los datos enviados por el formulario ($_POST).
    $config = new Config();
    $config->update($_POST);

    // Html::back() devuelve al usuario a la página anterior manteniendo la navegación estándar de GLPI.
    Html::back();

} else {
    global $CFG_GLPI;
    // Si no llega una acción válida, redirige al archivo de configuración principal.
    Html::redirect($CFG_GLPI['root_doc'] . '/plugins/lockassetfield/front/config.php');
}
