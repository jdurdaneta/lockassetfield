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

use CommonDBTM;
use Plugin;
use Html;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Clase ConfigGenricObject
 *
 * Gestiona la integración del plugin LockAssetField con los objetos
 * creados por el plugin GenericObject.
 *
 * Responsabilidades:
 *  - Mantener la tabla de configuración que indica qué tipos de objetos
 *    de GenericObject pueden ser gestionados por LockAssetField.
 *  - Construir una matriz de configuración para activar/desactivar
 *    tipos de objetos de GenericObject.
 *  - Proporcionar métodos auxiliares para:
 *      * Saber si GenericObject está activo.
 *      * Obtener los tipos de objetos creados en GenericObject.
 *      * Saber cuáles de esos tipos están registrados en ConfigField.
 */
class ConfigGenricObject extends CommonDBTM
{
    /** @var string Nombre del derecho de acceso principal para este objeto */
    public static $rightname = 'plugin_lockassetfield_config';

    /** @var string Nombre de la tabla de configuración específica para objetos genéricos */
    private static $table = 'glpi_plugin_lockassetfield_configgenricobjects';

    /**
     * Devuelve el nombre localizado del tipo actual.
     *
     * Se usa como etiqueta en menús, pestañas y otros elementos
     * de la interfaz de GLPI.
     *
     * @param int $nb Número de elementos (no se usa en este caso).
     *
     * @return string Nombre localizado.
     */
    public static function getTypeName($nb = 0)
    {
        return __('Gestión de objetos', 'lockassetfield');
    }

    /**
     * Devuelve el nombre del menú para este objeto.
     *
     * Es equivalente a getTypeName(), y se utiliza cuando el plugin
     * necesita etiquetar una entrada de menú asociada a esta clase.
     *
     * @return string Etiqueta (plural) para el menú.
     */
    public static function getMenuName(): string
    {
        return self::getTypeName();
    }

    /**
     * Construye la matriz de tipos de GenericObject y su estado de registro.
     *
     * La matriz se usa en el formulario de configuración para indicar
     * qué tipos de objetos de GenericObject están disponibles para
     * ser gestionados por LockAssetField.
     *
     * Estructura:
     *  - 'columns':
     *      * 'is_exist' → columna que indica si el tipo está registrado/activo.
     *  - 'rows':
     *      * clave: itemtype.
     *      * valor: ['label' => nombre visible, 'columns' => ['is_exist' => [...]]]
     *
     * @return array Matriz de configuración (filas y columnas).
     */
    public static function getMatrixAssetFields()
    {
        global $DB;

        // Obtenemos los itemtypes de GenericObject y si están registrados en ConfigField.
        $genericobjecttypes = self::getRegisteredObject();

        $matrix = [
            'rows' => [],
            'columns' => [
                'is_exist' => [
                    'label' => __('Activo'),
                    'title' => __('Activo')
                ],
            ]

        ];

        foreach ($genericobjecttypes as $itemtype => $is_exist) {
            $itemtype_obj = new $itemtype();
            $itemtype_label = $itemtype_obj->getTypeName(1);

            $sub_columns['is_exist'] = [
                'checked'  => $is_exist,
                'readonly' => !self::canUpdate(),
            ];
            $matrix['rows'][$itemtype] = [
                'label'   => __($itemtype_label),
                'columns' => $sub_columns
            ];
        }

        return $matrix;
    }

    /**
     * Muestra el formulario de configuración de objetos GenericObject.
     *
     * Permite seleccionar qué tipos de activos creados por el plugin GenericObject
     * deben estar disponibles para el bloqueo de campos gestionado por LockAssetField.
     *
     * Flujo:
     *  - Construye la matriz de itemtypes (getMatrixAssetFields()).
     *  - Muestra texto explicativo en una tabla de cabecera.
     *  - Renderiza una matriz de checkboxes (Html::showCheckboxMatrix)
     *    con una única columna "Activo".
     *  - Incluye un botón "Save" para enviar los cambios a ConfigField::getFormURL().
     *
     * @return void
     */
    public static function showConfigFieldForm()
    {

        $matrix = self::getMatrixAssetFields();
        $rows = $matrix['rows'];
        $columns = $matrix['columns'];

        echo '<table class="tab_cadre_fixe"><tbody>';
        echo '<tr><th>' . __('Añadir activos creados por el plugin GenericObject', 'prestamo') . '</th></tr>';
        echo '<tr><td>' . __('Seleccione los tipos de activos del plugin GenericObject que desea hacer disponibles para el bloqueo de campo', 'prestamo') . '</td></tr>';
        echo '</tbody></table>';

        echo '<div class="card">';
        echo '<div class="spaced p-3">';
        echo '<div class="field-container">';
        echo '<form method="post" action="' . ConfigField::getFormURL() . '">';

        // Mostrar la matriz de checkboxes (una columna 'Activo').
        Html::showCheckboxMatrix(
            $columns,
            $rows,
            [
                'title'           => __('Modelos'),
                'row_check_all'   => count($columns) > 1,
                'col_check_all'   => count($rows) > 1,
            ]
        );


        echo '<div class="d-flex justify-content-center gap-3 mt-3">';
        echo '<div class="w-auto">';
        if (self::canUpdate()) {
            echo '<button type="submit" name="add" value="' . __('Save') . '" class="ms-auto btn btn-primary">';
            echo '<i class="fas fa-save me-2"></i>' . __('Save');
            echo '</button>';
        }
        echo '</div>';
        echo '</div>';
        Html::closeForm();

        echo '</div>'; // END .field-container
        echo '</div>'; // END .spaced .p-3
        echo '</div>'; // END .card


        return;
    }

    /**
     * Indica qué tipos de GenericObject están registrados o no en ConfigField.
     *
     * Para cada itemtype de GenericObject:
     *  - Comprueba si existe un registro en `glpi_plugin_lockassetfield_configfields`
     *    mediante ConfigField::existInConfigField().
     *  - Devuelve un array asociativo:
     *      * clave => itemtype.
     *      * valor => 1 si está registrado, 0 si no.
     *
     * @return array Array [itemtype => 1|0] según esté o no registrado en ConfigField.
     */
    public static function getRegisteredObject()
    {
        global $DB;

        $genericobjecttypes = self::getItemtypeGenericObject();

        $result = [];

        foreach ($genericobjecttypes as $itemtype) {

            if(class_exists($itemtype)){
                // Se verifica si existe registrado en la tabla de ConfigFields.
                $result[$itemtype] = ConfigField::existInConfigField($itemtype) ? 1 : 0;
            }
        }
        return $result;
    }

    /**
     * Verificar si el plugin GenericObject está activado en GLPI.
     *
     * @return bool True si GenericObject está activo, false en caso contrario.
     */
    public static function isActiveGenericObject(): bool
    {
        return Plugin::isPluginActive('genericobject');
    }

    /**
     * Obtiene todos los tipos de objetos definidos en el plugin GenericObject.
     *
     * Devuelve un array con los registros devueltos por \PluginGenericobjectType::find(),
     * siempre que el plugin GenericObject esté activo. Si no lo está, devuelve un array vacío.
     *
     * @return array Datos de los tipos de GenericObject (cada elemento es una fila de find()).
     */
    public static function getGenericObjectCreated(): array
    {
        $genericObjectArray = [];
        if (Plugin::isPluginActive('genericobject')) {
            $genericObjectType = new \PluginGenericobjectType();
            $genericObjectArray = $genericObjectType->find();
        }
        return $genericObjectArray;
    }

    /**
     * Obtiene los itemtypes correspondientes a los objetos de GenericObject.
     *
     * A partir de los datos devueltos por getGenericObjectCreated():
     *  - Extrae el campo 'itemtype' de cada registro.
     *  - Aplica ucfirst() para normalizar el nombre de clase.
     *
     * @return array Lista de itemtypes de GenericObject registrados (como nombres de clase).
     */
    public static function getItemtypeGenericObject(): array
    {
        $genericObjectArray = self::getGenericObjectCreated();
        return array_map(function ($item) {
            return ucfirst($item['itemtype']);
        }, $genericObjectArray);
    }
}
