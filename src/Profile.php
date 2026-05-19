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

namespace GlpiPlugin\Lockassetfield;

use Html;
use CommonDBTM;
use CommonGLPI;
use Profile as Glpi_Profile;
use GlpiPlugin\Lockassetfield\Config;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Clase Profile
 *
 * Gestiona la integración de los permisos específicos del plugin LockAssetField
 * dentro de los perfiles de GLPI:
 *
 * - Añade una pestaña en la ficha de configuración de perfiles.
 * - Declara los derechos del plugin asociados al objeto Config.
 * - Muestra la matriz de permisos para que el administrador pueda
 *   activar/desactivar los derechos por perfil.
 */
final class Profile extends CommonDBTM
{
    /**
     * Devuelve el nombre de la pestaña a mostrar dentro del perfil GLPI.
     *
     * Solo añade la pestaña para:
     * - Perfiles de tipo Glpi_Profile.
     * - Perfiles que tengan un ID válido.
     * - Perfiles cuya interfaz no sea "helpdesk" (es decir, interfaz central).
     *
     * @param CommonGLPI $item         Objeto perfil GLPI.
     * @param int        $withtemplate Indicador de plantilla (0|1) — no se utiliza.
     *
     * @return string Nombre de la pestaña si aplica, o cadena vacía en caso contrario.
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (
            $item instanceof Glpi_Profile
            && $item->getField('id') && $item->fields['interface'] != 'helpdesk'
        ) {
            // La etiqueta de la pestaña usa el nombre del tipo de configuración del plugin.
            return self::createTabEntry(
                Config::getTypeName(),
                0,
                Config::class,
                Config::getIcon()
            );
        }
        return '';
    }

    /**
     * Muestra el contenido de la pestaña del plugin dentro del perfil GLPI.
     *
     * Cuando el item es un perfil válido y pertenece a la interfaz central,
     * se llama a showForProfile() para mostrar la matriz de permisos del plugin.
     *
     * @param CommonGLPI $item         Objeto perfil GLPI.
     * @param int        $tabnum       Número de pestaña activa (no se usa en este caso).
     * @param int        $withtemplate Indicador de plantilla (0|1) — no se utiliza.
     *
     * @return bool True si se muestra correctamente o si no aplica.
     */
    public static function displayTabContentForItem(
        CommonGLPI $item,
        $tabnum = 1,
        $withtemplate = 0
    ) {
        if (
            $item instanceof Glpi_Profile
            && $item->getField('id') && ($item->fields['interface'] != 'helpdesk')
        ) {
            return self::showForProfile($item->getID());
        }

        return true;
    }

    /**
     * Devuelve la lista de derechos gestionados por el plugin.
     *
     * Cada entrada describe:
     * - itemtype: clase a la que se aplican los derechos (en este caso Config).
     * - label: etiqueta visible del conjunto de derechos.
     * - field: nombre del campo de derecho (string usado en glpi_profilerights).
     *
     * Esta información se utiliza al mostrar la matriz de permisos.
     *
     * @param bool $all Si es true, podría devolver en el futuro más derechos (no utilizado actualmente).
     *
     * @return array Lista de derechos definidos (itemtype, label, field).
     */
    public static function getAllRights($all = false)
    {
        $rights = [
            [
                'itemtype' => Config::class,
                'label'    => Config::getTypeName(),
                'field'    => Config::$rightname,
            ]
        ];

        return $rights;
    }

    /**
     * Muestra la matriz de derechos para el perfil especificado.
     *
     * Pasos que realiza:
     * - Carga el perfil a partir de su ID.
     * - Abre un formulario apuntando al formURL del propio perfil.
     * - Construye la matriz de selección de permisos usando displayRightsChoiceMatrix()
     *   y los derechos definidos por getAllRights().
     * - Añade un botón de guardado para enviar los cambios.
     *
     * @param int $profiles_id ID del perfil GLPI.
     *
     * @return void
     */
    public static function showForProfile($profiles_id = 0)
    {
        $profile = new Glpi_Profile();
        $profile->getFromDB($profiles_id);

        echo "<div class='firstbloc'>";
        echo "</div>";

        echo "<form method='post' action='" . $profile->getFormURL() . "'>";

        // Obtiene los permisos definidos en el plugin.
        $all_rights = self::getAllRights();
        // Opciones de configuración para la matriz de derechos.
        $matrix_options['title'] = __('General');

        // Muestra la matriz de selección de permisos del plugin.
        $profile->displayRightsChoiceMatrix($all_rights, $matrix_options);

        echo "<div class='center'>";
        echo Html::hidden('id', ['value' => $profiles_id]);
        echo Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
        echo "</div>\n";
        Html::closeForm();
    }

    /**
     * Elimina los derechos asociados al plugin durante la desinstalación.
     *
     * Borra de la tabla glpi_profilerights todos los registros cuyo nombre de
     * derecho comience por 'plugin_lockassetfield_'. De esta forma se limpian
     * las referencias a permisos del plugin para todos los perfiles.
     *
     * @return void
     */
    public static function uninstall()
    {
        global $DB;

        // Borra los registros de derechos del plugin en glpi_profilerights.
        $query = "DELETE
                  FROM `glpi_profilerights`
                  WHERE `name` LIKE 'plugin_lockassetfield_%'";
        $DB->queryOrDie($query, $DB->error());
    }
}
