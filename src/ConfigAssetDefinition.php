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
use Glpi\Asset\AssetDefinition;
use Html;
use Plugin;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Clase ConfigGenricObject
 *
 * Gestiona la integración del plugin LockAssetField con los objetos
 * creados por el plugin AssetDefinition.
 *
 * Responsabilidades:
 *  - Mantener la tabla de configuración que indica qué tipos de objetos
 *    de AssetDefinition pueden ser gestionados por LockAssetField.
 *  - Construir una matriz de configuración para activar/desactivar
 *    tipos de objetos de AssetDefinition.
 *  - Proporcionar métodos auxiliares para:
 *      * Saber si AssetDefinition está activo.
 *      * Obtener los tipos de objetos creados en AssetDefinition.
 *      * Saber cuáles de esos tipos están registrados en ConfigField.
 */
class ConfigAssetDefinition extends CommonDBTM
{
    /** @var string Nombre del derecho de acceso principal para este objeto */
    public static $rightname = 'plugin_lockassetfield_config';

    
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
        return __('Assets Definition');
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
     * Construye la matriz de tipos de AssetDefinition y su estado de registro.
     *
     * La matriz se usa en el formulario de configuración para indicar
     * qué tipos de objetos de AssetDefinition están disponibles para
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

        // Obtenemos los itemtypes de AssetDefinition y si están registrados en ConfigField.
        $AssetDefinitiontypes = self::getRegisteredObject();

        $matrix = [
            'rows' => [],
            'columns' => [
                'is_exist' => [
                    'label' => __('Activo'),
                    'title' => __('Activo')
                ],
            ]

        ];

        foreach ($AssetDefinitiontypes as $itemtype => $is_exist) {
            $itemtype_obj = new $itemtype();
            $itemtype_label = ucfirst($itemtype_obj->getTypeName(1));

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
     * Muestra el formulario de configuración de objetos AssetDefinition.
     *
     * Permite seleccionar qué tipos de activos creados por el plugin AssetDefinition
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
        echo '<tr><th>' . __('Añadir activos creados por Definición de Activos', 'prestamo') . '</th></tr>';
        echo '<tr><td>' . __('Seleccione los tipos de activos que desea hacer disponibles para el bloqueo de campo', 'prestamo') . '</td></tr>';
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
     * Indica qué tipos de AssetDefinition están registrados o no en ConfigField.
     *
     * Para cada itemtype de AssetDefinition:
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

        $AssetDefinitiontypes = self::getItemtypeAssetDefinition();

        $result = [];

        foreach ($AssetDefinitiontypes as $itemtype) {

            if(class_exists($itemtype)){
                // Se verifica si existe registrado en la tabla de ConfigFields.
                $result[$itemtype] = ConfigField::existInConfigField($itemtype) ? 1 : 0;
            }
        }
        return $result;
    }

    /**
     * Verificar si el plugin AssetDefinition está activado en GLPI.
     *
     * @return bool True si AssetDefinition está activo, false en caso contrario.
     */
    public static function isActiveAssetDefinition(): bool
    {
        return Plugin::isPluginActive('AssetDefinition');
    }

    /**
     * Obtiene todos los tipos de objetos definidos en el plugin AssetDefinition.
     *
     * Devuelve un array con los registros devueltos por \PluginAssetDefinitionType::find(),
     * siempre que el plugin AssetDefinition esté activo. Si no lo está, devuelve un array vacío.
     *
     * @return array Datos de los tipos de AssetDefinition (cada elemento es una fila de find()).
     */
    public static function getAssetDefinitionCreated(): array
    {
        $AssetDefinitionArray = [];
        $assets = new AssetDefinition();

        /* if (Plugin::isPluginActive('AssetDefinition')) {
            $AssetDefinitionType = new \PluginAssetDefinitionType();
            $AssetDefinitionArray = $AssetDefinitionType->find();
        } */
        return $assets->find(['is_active' => 1], ['id ASC']);
    }

    /**
     * Obtiene los itemtypes correspondientes a los objetos de AssetDefinition.
     *
     * A partir de los datos devueltos por getAssetDefinitionCreated():
     *  - Extrae el campo 'itemtype' de cada registro.
     *  - Aplica ucfirst() para normalizar el nombre de clase.
     *
     * @return array Lista de itemtypes de AssetDefinition registrados (como nombres de clase).
     */
    public static function getItemtypeAssetDefinition(): array
    {
        $AssetDefinitionArray = self::getAssetDefinitionCreated();
        $res_definitions = [];
        foreach ($AssetDefinitionArray as $row) {
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

            /* $definitions[] = [
                'id'          => (int) $definition->fields['id'],
                'name'        => (string) ($definition->fields['name'] ?? ''),
                // 'label'       => self::getDefinitionLabel($definition),
                'system_name' => (string) ($definition->fields['system_name'] ?? ''),
                'itemtype'    => $itemtype,
            ]; */
            /* $res_definitions[] = [
                'itemtype'    => $itemtype,
            ]; */
            $res_definitions[] = $itemtype;
        }


        return $res_definitions;
        /* return array_map(function ($item) {
            return ucfirst($item['itemtype']);
        }, $$res_definitions); */
    }
}
