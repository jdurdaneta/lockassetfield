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

include ('../../../inc/includes.php');

// Validación de derechos de acceso a la configuración del plugin.
// Solo los usuarios con el derecho READ asociado a Config::$rightname
// pueden acceder a esta página.
Session::checkRight(Config::$rightname, READ);

// Instancia del modelo principal del plugin (configuración global).
// Se carga el registro con id = 1, que actúa como configuración única.
$config = new Config();
$config->getFromDB(1); // Se toma el primero pero no sus 

// Cabecera estándar de GLPI para la sección de configuración del plugin.
// Define:
//  - Título de la página (Config::getTypeName()).
//  - URL actual ($_SERVER['PHP_SELF']).
//  - Sección principal ("config").
//  - Itemtype asociado (Config::class) para iconos / breadcrumb.
//  - Nombre del submódulo ("Lock Asset Field").
Html::header(
    Config::getTypeName(),    // Título de la página (traducción singular/plural gestionada por GLPI)
    $_SERVER['PHP_SELF'],     // URL actual
    "config",                 // Pestaña/sección de GLPI en la que se muestra
    Config::class,            // Itemtype asociado para iconos/breadcrumbs
    "Lock Asset Field"        // Submódulo / ancla de menú dentro del plugin
);

// Si el usuario tiene derecho de ver el objeto, mostramos las pestañas de configuración.
// showTabsContent() se encarga de mostrar el contenido según las tabs definidas en Config.
if ($config->canView()) {
    // Renderiza el contenido de pestañas estándar de GLPI para el itemtype Config.
    $config->showTabsContent();
    // Config::showConfigForm(); // Ejemplo de posible llamada directa a un formulario específico (comentado).
} else {
    // Muestra página de error de permisos insuficientes.
    Html::displayRightError();
}

// Pie de página estándar de GLPI para cerrar correctamente la página HTML.
Html::footer();
