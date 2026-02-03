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
use Session;
use Dropdown;
use CommonDBTM;
use GlpiPlugin\Lockassetfield\Config;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Clase ConfigField
 *
 * Representa la configuración de bloqueo de campos por tipo de activo.
 *
 * Responsabilidades principales:
 *  - Mantener en base de datos, por `itemtype`, qué campos pueden estar bloqueados
 *    (número de serie, inventario, fabricante, modelo, tipo, estado, etc.).
 *  - Proporcionar matrices de configuración para la interfaz (configuración de campos
 *    y configuración de bloqueo por estado).
 *  - Resolver, para un `itemtype` concreto, qué campos deben bloquearse en los hooks.
 */
class ConfigField extends CommonDBTM
{
    /** @var string Nombre del derecho de acceso principal para este objeto */
    public static $rightname = 'plugin_lockassetfield_config';

    /** @var string Nombre de la tabla de configuración de campos bloqueados */
    private static $table = 'glpi_plugin_lockassetfield_configfields';

    /**
     * Devuelve el nombre localizado del tipo actual.
     *
     * Este nombre se utiliza en la interfaz (búsquedas, pestañas, etc.),
     * aunque esta clase se orienta principalmente a la configuración.
     *
     * @param int $nb Número de elementos (no se utiliza en este caso).
     *
     * @return string Nombre localizado del tipo.
     */
    public static function getTypeName($nb = 0)
    {
        return __('Bloqueo de campos', 'lockassetfield');
    }

    /**
     * Obtiene los tipos de activos soportados y, opcionalmente,
     * sus campos bloqueables.
     *
     * Cuando `$fields` es:
     *  - `null`       → devuelve un array plano con los itemtypes.
     *  - `'lockfields'` → devuelve las filas completas con los campos
     *                     *_locked para construir matrices de configuración.
     *
     * Si el `itemtype` no existe como clase en GLPI y tampoco está en la lista
     * de tipos nativos soportados por el plugin (`Config::lockAssetFieldType()`),
     * se elimina su registro de la tabla de configuración.
     *
     * @param string|null $fields Modo de retorno: null (solo itemtypes) o 'lockfields'.
     *
     * @return array Lista de itemtypes o filas completas según el modo.
     */
    public static function getSupportedAssetTypes($fields = null)
    {
        global $DB;

        $res = [];
        $iterator = $DB->request([
            'SELECT' => ['itemtype', 'serial_locked', 'otherserial_locked', 'manufacturers_id_locked', 'models_id_locked', 'types_id_locked'],
            'FROM'   => self::$table,
            'ORDER'  => 'id ASC'
        ]);

        foreach ($iterator as $row) {
            if (!class_exists($row['itemtype'])) {
                // Verificamos si no es un activo nativo de GLPI; si no lo es, lo eliminamos de nuestra tabla
                if (!in_array($row['itemtype'], Config::lockAssetFieldType())) {
                    $DB->delete(
                        self::$table,
                        ['itemtype' => $row['itemtype']]
                    );
                }
            } else {
                if ($fields === 'lockfields') {
                    $res[] = $row;
                } else {
                    $res[] = $row['itemtype'];
                }
            }
        }
        return $res;
    }

    /**
     * Obtiene la etiqueta traducida para un campo bloqueado.
     *
     * Aplica reglas específicas:
     *  - Si el nombre termina en `types_id`, se etiqueta como "Type".
     *  - Si termina en `models_id`, se etiqueta como "Model".
     *  - Para otros campos conocidos (`serial`, `otherserial`, `manufacturers_id`, `states_id`)
     *    usa etiquetas específicas en castellano.
     *
     * @param string $field Nombre del campo (por ejemplo, 'serial', 'otherserial', 'states_id').
     *
     * @return string Etiqueta traducida del campo.
     */
    public static function getLockfieldFieldLabel($field): string
    {
        if (str_ends_with($field, 'types_id')) {
            return __("Type");
        }
        if (str_ends_with($field, 'models_id')) {

            return __("Model");
        }
        $fields = [
            'serial'            => __('Número de serie'),
            'otherserial'       => __('Número de inventario'),
            'manufacturers_id'  => __('Fabricantes'),
            'states_id'         => __('Estado', 'glpi')
        ];
        return $fields[$field];
    }

    /**
     * Construye la matriz de tipos de activos y sus campos bloqueables.
     *
     * La estructura devuelta es del tipo:
     *  - 'columns' → definición de cada columna (campo bloqueable).
     *  - 'rows'    → para cada itemtype, indica:
     *      - etiqueta del tipo de activo.
     *      - qué columnas están marcadas (checked) y si son editables (readonly).
     *
     * Se utiliza principalmente en el formulario de configuración de bloqueo de campos.
     *
     * @return array Matriz con filas (itemtypes) y columnas (campos bloqueables).
     */
    public static function getMatrixAssetFields()
    {
        global $DB;

        $matrix = [
            'rows' => [],
            'columns' => [
                'serial_locked' => [
                    'label' => __('número de serie'),
                    'title' => __('número de serie')
                ],
                'otherserial_locked' => [
                    'label' => __('número de inventario'),
                    'title' => __('número de inventario')
                ],
                'manufacturers_id_locked' => [
                    'label' => __('manufacturer'),
                    'title' => __('manufacturer')
                ],
                'models_id_locked' => [
                    'label' => __('modelo'),
                    'title' => __('modelo')
                ],
                'types_id_locked' => [
                    'label' => __('tipo'),
                    'title' => __('tipo')
                ],
            ]

        ];

        // Obtenemos los itemtype registrados en nuestra tabla con sus flags *_locked
        $assettypes =  self::getSupportedAssetTypes('lockfields');

        foreach ($assettypes as $row) {
            foreach ($row as $field => $val) {
                if ($field === 'itemtype') {
                    $itemtype = $val;

                    $itemtype_obj = new $itemtype();

                    $itemtype_label = $itemtype_obj->getTypeName(1);
                }
                $sub_columns[$field] = [
                    'checked'  => $val,
                    'readonly' => !self::canUpdate()
                ];
                $matrix['rows'][$itemtype] = [
                    'label'   => __($itemtype_label),
                    'columns' => $sub_columns
                ];
            }
        }

        return $matrix;
    }

    /**
     * Muestra el formulario de configuración de bloqueo de campos por tipo de activo.
     *
     * Uso:
     *  - Obtiene la matriz de tipos de activo y campos bloqueables.
     *  - Pinta texto introductorio explicando cómo marcar los campos.
     *  - Renderiza una tabla de checkboxes mediante Html::showCheckboxMatrix(),
     *    permitiendo al usuario indicar los campos que deben bloquearse.
     *  - Incluye un botón "Save" si el usuario tiene derecho a actualizar.
     *
     * @return bool Siempre true tras renderizar el formulario.
     */
    public static function showConfigFieldForm()
    {

        $matrix = self::getMatrixAssetFields();
        $rows = $matrix['rows'];
        $columns = $matrix['columns'];

        echo '<table class="tab_cadre_fixe"><tbody>';
        echo '<tr><th>' .  __('Configuración de bloqueo de campos de activos', 'lockassetfield') . '</th></tr>';
        echo '<tr><td>' . __('Marque las casillas correspondientes para indicar qué campos deben permanecer bloqueados en los formularios de cada activo.', 'prestamo') . '</td></tr>';
        echo '</tbody></table>';
        echo '<div class="card">';
        echo '<div class="spaced p-3">';
        echo '<form method="post" action="' . self::getFormURL() . '">';

        // Mostrar la matriz de checkboxes
        Html::showCheckboxMatrix(
            $columns,
            $rows,
            [
                'title'           => __('Activos'),
                'row_check_all'   => count($columns) > 1,
                'col_check_all'   => count($rows) > 1,
            ]
        );

        echo '<div class="d-flex justify-content-center gap-3 mt-3">';
        echo '<div class="w-auto">';
        if (self::canUpdate()) {
            echo '<button type="submit" name="update" value="' . __('Save') . '" class="ms-auto btn btn-primary">';
            echo '<i class="fas fa-save me-2"></i>' . __('Save');
            echo '</button>';
        }
        echo '</div>';
        echo '</div>';

        Html::closeForm();
        echo "</div>";
        echo "</div>";
    }

    /**
     * Muestra el formulario de configuración de bloqueo por estados.
     *
     * Permite definir, para cada tipo de activo, en qué estados (`glpi_states`)
     * debe bloquearse el campo `states_id`. Flujo:
     *
     *  - Obtiene los itemtypes registrados.
     *  - Obtiene el listado de estados desde `glpi_states`.
     *  - Para cada tipo de activo, muestra:
     *      - Nombre del tipo.
     *      - Dropdown múltiple con los estados seleccionables.
     *  - Incluye botón "Save" si el usuario puede actualizar.
     *
     * @return void
     */
    public static function showConfigFieldStateForm(): void
    {
        // Obtenemos los itemtypes
        $fieldSatesTypes = self::getSupportedAssetTypes();

        // Obtenemos los estados para el dropdown multiple
        $elements = self::getStates();

        echo '<table class="tab_cadre_fixe"><tbody>';
        echo '<tr><th>' . __('Configuración de bloqueo de campo cambio de estado', 'lockassetfield') . '</th></tr>';
        echo '<tr><td>' . __('Seleccione en qué estados del dispositivo el campo Cambiar estado debe permanecer bloqueado.', 'prestamo') . '</td></tr>';
        echo '</tbody></table>';

        echo '<div class="card">';
        echo '<div class="spaced p-3">';
        echo '<div class="field-container">';
        echo '<form method="post" action="' . self::getFormURL() . '">';
        echo '<div class ="">';
        echo '<table class="tab_cadre_fixe">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Activo</th>';
        echo '<th>Estado</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($fieldSatesTypes as $fieldSatesType) {
            // obtenemos el objeto
            $configFieldSate = new self();
            if (!$configFieldSate->getFromDBByCrit(['itemtype' => $fieldSatesType])) {
                continue;
            }
            // Obtenemos el objeto del itemtype
            $itemtype = new $fieldSatesType();

            $name_input_hidden = $fieldSatesType . '[id]';
            $name_input_state = $fieldSatesType . '[state_ids]';

            echo '<input type="hidden" name="' . $name_input_hidden . '" value="' . $configFieldSate->fields['id'] . '" >';
            echo '<tr>';
            // El nombre del itemtype
            echo '<td class="tab_bg_1" style="width:40%">' . __($itemtype->getTypeName(1)) . '</td>';

            // Dropdown múltiple de los estados de los activos
            echo '<td style="width:60%">';
            Dropdown::showFromArray($name_input_state, $elements, [
                'multiple'   => true, // Habilitar selección múltiple
                'values'      => $configFieldSate->fields['state_ids'] !== null ? json_decode($configFieldSate->fields['state_ids']) : [],   // Valor preseleccionado (opcional)
                'rand'       => mt_rand(), // ID único
                'comments'   => __('Seleccione uno o varios estados'),
                'display'    => true, // Hacerlo visible
                'width'     => 'auto',
                'required' => false,
                'width' => '100%',
                'readonly' => !self::canUpdate()
            ]);
            echo '</td>';

            echo '</tr>';
        }

        echo '</tbody>';
        echo "</table>";
        echo "</div>";

        echo '<div class="d-flex justify-content-center gap-3 mt-3">';
        echo '<div class="w-auto">';
        if (self::canUpdate()) {
            echo '<button type="submit" name="update_states" value="' . __('Save') . '" class="ms-auto btn btn-primary">';
            echo '<i class="fas fa-save me-2"></i>' . __('Save');
            echo '</button>';
        }
        echo '</div>';
        echo '</div>';
        Html::closeForm();
        echo "</div>";
        echo "</div>";
    }

    /**
     * Obtiene los campos bloqueados para un tipo de item dado.
     *
     * A partir de la fila de configuración en `glpi_plugin_lockassetfield_configfields`
     * para el `itemtype`:
     *  - Comprueba cada columna *_locked.
     *  - Si está activa (valor 1), añade el nombre del campo correspondiente
     *    a la lista resultante.
     *  - Para `models_id_locked` y `types_id_locked`:
     *      - Obtiene la clase de modelo/tipo asociada al itemtype.
     *      - Calcula el nombre del campo *_id correspondiente a la tabla
     *        de modelo/tipo sin el prefijo `glpi_`.
     *
     * El resultado se utiliza en el hook `pre_item_update` para impedir cambios.
     *
     * @param string $itemtype Nombre de la clase del tipo de item (por ejemplo 'Computer').
     *
     * @return array Lista de nombres de campos que deben considerarse bloqueados.
     */
    public static function getLockedFieldsForItemType($itemtype): array
    {
        global $DB;

        $fields = [];
        $iterator = $DB->request([
            'SELECT' => ['itemtype', 'serial_locked', 'otherserial_locked', 'manufacturers_id_locked', 'models_id_locked', 'types_id_locked'],
            'FROM' => self::$table,
            'WHERE' => [
                'itemtype' => $itemtype,
            ]
        ]);


        foreach ($iterator as $row) {
            foreach ($row as $col => $val)
                if ($val === 1) {
                    $field = str_replace('_locked', '', $col);
                    if ($field === 'models_id') {
                        // Obtenemos el objeto por itemtype
                        $item = getItemForItemtype($itemtype);
                        // Obtenemos la clase del model
                        $model_class = $item->getModelClass();
                         if($model_class !== null ){
                             // obtenemos el nombre da la tabla de itemModel pero sin el sufijo glpi_
                             $itemtype_column = str_replace('glpi_', '', getTableForItemType($model_class));
                             $fields[] = $itemtype_column . '_id';
                         }
                    } elseif ($field === 'types_id') {
                        // Obtenemos el objeto por itemtype
                        $item = getItemForItemtype($itemtype);
                        // Obtenemos la clase del type
                        $type_class = $item->getTypeClass();
                        if($type_class !== null ){
                            // obtenemos el nombre da la tabla de itemTypes pero sin el sufijo glpi_
                            $itemtype_column = str_replace('glpi_', '', getTableForItemType($type_class));
                            $fields[] = $itemtype_column . '_id';
                        }
                    } else {
                        $fields[] = $field;
                    }
                }
        }

        return $fields;
    }

    /**
     * Determina si el campo de estado (`states_id`) debe estar bloqueado
     * para un `itemtype` y un estado concretos.
     *
     * Lógica:
     *  - Lee la columna `state_ids` (JSON) para el `itemtype`.
     *  - Si existe y es un array, comprueba si `$states_id` está en la lista.
     *  - Si coincide, devuelve un array con `['states_id']` para indicar
     *    que dicho campo debe tratarse como bloqueado.
     *  - En caso contrario, devuelve false.
     *
     * @param string $itemtype  Clase del tipo de item.
     * @param int    $states_id ID del estado actual del item.
     *
     * @return array|bool Array con 'states_id' si está bloqueado, false en caso contrario.
     */
    public static function isFieldStateLocked($itemtype, $states_id): array|bool
    {
        global $DB;

        $fields = [];
        $iterator = $DB->request([
            'SELECT' => ['state_ids'],
            'FROM' => self::$table,
            'WHERE' => [
                'itemtype' => $itemtype
            ],
            'LIMIT' => 1
        ]);

        foreach ($iterator as $row) {
            if (!empty($row['state_ids'])) {
                // Decodificar JSON en array
                $decoded = json_decode($row['state_ids'], true);

                // Validar que sea realmente un array
                if (is_array($decoded)) {
                    $state_ids = $decoded;
                }
            }
        }

        // Si sigue vacío, no hay estados bloqueados
        if (empty($state_ids)) {
            return false;
        }

        // Ahora sí es seguro llamar a in_array()
        return in_array($states_id, $state_ids) ? ['states_id'] : false;
    }

    /**
     * Obtiene los estados configurados en GLPI para usarlos en los dropdowns.
     *
     * Lee la tabla `glpi_states` y devuelve un array asociativo:
     *  - clave: ID del estado.
     *  - valor: nombre del estado.
     *
     * Se utiliza en el formulario de bloqueo por estado.
     *
     * @return array Lista de estados indexada por id => name.
     */
    public static function getStates(): array
    {
        global $DB;

        $glpi_states = $DB->request([
            'SELECT' => ['id', 'name'],
            'FROM'   => 'glpi_states',
            'ORDER'  => 'name'
        ]);

        if (count($glpi_states) > 0) {
            // Creamos Array para los options de Dropdown multiple
            foreach ($glpi_states as $data) {
                $elements[$data["id"]] = $data["name"];
            }
        } else {
            $elements = [];
        }
        return $elements;
    }

    /**
     * Comprueba si un itemtype está registrado en la tabla de configuración.
     *
     * Verifica si existe un registro en `glpi_plugin_lockassetfield_configfields`
     * para el `itemtype` dado.
     *
     * @param string $itemtype Nombre del itemtype.
     *
     * @return bool True si existe un registro, false en caso contrario.
     */
    public static function existInConfigField($itemtype): bool
    {

        $configfield = new self;

        if (!$configfield->getFromDBByCrit(['itemtype' => $itemtype])) {
            return false;
        }
        return true;
    }

    /**
     * Proceso de instalación de la tabla de configuración de campos.
     *
     * Acciones:
     *  - Crea la tabla `glpi_plugin_lockassetfield_configfields` si no existe.
     *  - Añade una fila por cada itemtype soportado (`Config::lockAssetFieldType()`),
     *    siempre que la clase exista y no tenga ya registro.
     *
     * @return void
     */
    public static function install()
    {
        /** @var DBmysql $DB */
        /** @var array $GENINVENTORYNUMBER_TYPES */
        global $DB;

        $table = getTableForItemType(__CLASS__);

        if (!$DB->tableExists($table)) {
            $query = "CREATE TABLE IF NOT EXISTS `$table` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `plugin_lockassetfield_configs_id` INT UNSIGNED NOT NULL DEFAULT 1,
                `itemtype` VARCHAR(100) NOT NULL DEFAULT '',
                `state_ids` LONGTEXT DEFAULT NULL,
                `serial_locked` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `otherserial_locked` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `manufacturers_id_locked` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `models_id_locked` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `types_id_locked` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `date_creation` TIMESTAMP NULL DEFAULT NULL,
                `date_mod` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `plugin_lockassetfield_configs_id` (`plugin_lockassetfield_configs_id`),
                UNIQUE KEY `itemtype` (`itemtype`),
                KEY `date_mod` (`date_mod`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";

            $DB->queryOrDie($query, $DB->error());
        }
        $field = new self();

        $lockAssetFieldType = Config::lockAssetFieldType();

        foreach ($lockAssetFieldType as $type) {
            if (class_exists($type) && !countElementsInTable($table, ['itemtype' => $type])) {
                $input['plugin_lockassetfield_configs_id']  = 1;
                $input['itemtype']                          = $type;
                $field->add($input);
            }
        }
    }

    /**
     * Proceso de desinstalación: elimina la tabla de configuración de campos.
     *
     * @return void
     */
    public static function uninstall()
    {
        global $DB;
        $DB->dropTable(getTableForItemType(__CLASS__));
    }
}
