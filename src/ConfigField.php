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

namespace GlpiPlugin\Lockassetfield;

use CommonDBTM;
use Dropdown;
use Html;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Configuración de campos bloqueados por tipo de activo.
 */
class ConfigField extends CommonDBTM
{
    /**
     * Derecho principal asociado a este objeto.
     *
     * @var string
     */
    public static $rightname = 'plugin_lockassetfield_config';

    /**
     * Habilita histórico de cambios en la configuración de campos.
     *
     * @var bool
     */
    public $dohistory = true;

    /**
     * Tabla de configuración.
     *
     * @var string
     */
    private static $table = 'glpi_plugin_lockassetfield_configfields';

    /**
     * Devuelve el nombre localizado del tipo.
     *
     * @param int $nb Número de elementos.
     *
     * @return string
     */
    public static function getTypeName($nb = 0): string
    {
        return __('Bloqueo de campos', 'lockassetfield');
    }

    /**
     * Sincroniza la tabla de configuración con los tipos soportados en GLPI 11.
     *
     * - Inserta los tipos estándar que no existan aún.
     * - Mantiene los activos personalizados seleccionados desde Gestión de objetos.
     * - Elimina solo registros obsoletos que NO sean activos personalizados GLPI 11.
     * - Migra itemtypes heredados de GenericObject a custom assets del core.
     *
     * @return void
     */
    public static function syncSupportedAssetTypes(): void
    {
        global $DB;

        self::migrateLegacyGenericObjectItemtypes();

        $table = getTableForItemType(__CLASS__);

        $standardTypes = Config::lockAssetFieldType();
        $customTypes = ConfigAssetObject::getAvailableCustomAssetItemtypes();

        $allowedTypes = array_values(
            array_unique(
                array_merge(
                    $standardTypes,
                    $customTypes
                )
            )
        );

        foreach ($standardTypes as $itemtype) {
            if (!countElementsInTable($table, ['itemtype' => $itemtype])) {
                $field = new self();
                $field->add([
                    'plugin_lockassetfield_configs_id' => 1,
                    'itemtype'                         => $itemtype,
                ]);
            }
        }

        foreach (
            $DB->request(
                [
                    'SELECT' => ['id', 'itemtype'],
                    'FROM'   => $table,
                ]
            ) as $row
        ) {
            $itemtype = (string) $row['itemtype'];

            if (
                !in_array($itemtype, $allowedTypes, true)
                && !str_starts_with($itemtype, 'Glpi\\CustomAsset\\')
            ) {
                $DB->delete(
                    $table,
                    [
                        'id' => $row['id'],
                    ]
                );
            }
        }
    }

    /**
     * Obtiene los tipos soportados y, opcionalmente, sus flags *_locked.
     *
     * @param string|null $fields null para devolver itemtypes,
     *                            'lockfields' para devolver filas completas.
     *
     * @return array
     */
    public static function getSupportedAssetTypes($fields = null): array
    {
        global $DB;

        self::syncSupportedAssetTypes();

        $standardTypes = Config::lockAssetFieldType();
        $customTypes = ConfigAssetObject::getAvailableCustomAssetItemtypes();

        $allowedTypes = array_values(
            array_unique(
                array_merge(
                    $standardTypes,
                    $customTypes
                )
            )
        );

        $result = [];

        $iterator = $DB->request([
            'SELECT' => [
                'id',
                'itemtype',
                'serial_locked',
                'otherserial_locked',
                'manufacturers_id_locked',
                'models_id_locked',
                'types_id_locked',
            ],
            'FROM'  => self::$table,
            'ORDER' => 'id ASC',
        ]);

        foreach ($iterator as $row) {
            $itemtype = (string) $row['itemtype'];

            if (
                !in_array($itemtype, $allowedTypes, true)
                && !str_starts_with($itemtype, 'Glpi\\CustomAsset\\')
            ) {
                $DB->delete(
                    self::$table,
                    [
                        'id' => $row['id'],
                    ]
                );
                continue;
            }

            if ($fields === 'lockfields') {
                $result[] = $row;
            } else {
                $result[] = $itemtype;
            }
        }

        return $result;
    }

    /**
     * Devuelve la etiqueta traducida de un campo bloqueado.
     *
     * @param string $field Nombre del campo.
     *
     * @return string
     */
    public static function getLockfieldFieldLabel($field): string
    {
        if (str_ends_with($field, 'types_id')) {
            return __('Type');
        }

        if (str_ends_with($field, 'models_id')) {
            return __('Model');
        }

        $fields = [
            'serial'           => __('Número de serie'),
            'otherserial'      => __('Número de inventario'),
            'manufacturers_id' => __('Fabricantes'),
            'states_id'        => __('Estado', 'glpi'),
        ];

        return $fields[$field] ?? $field;
    }

    /**
     * Devuelve la matriz de tipos de activos y campos bloqueables.
     *
     * @return array<string, mixed>
     */
    public static function getMatrixAssetFields(): array
    {
        $matrix = [
            'rows'    => [],
            'columns' => [
                'serial_locked' => [
                    'label' => __('número de serie'),
                    'title' => __('número de serie'),
                ],
                'otherserial_locked' => [
                    'label' => __('número de inventario'),
                    'title' => __('número de inventario'),
                ],
                'manufacturers_id_locked' => [
                    'label' => __('manufacturer'),
                    'title' => __('manufacturer'),
                ],
                'models_id_locked' => [
                    'label' => __('modelo'),
                    'title' => __('modelo'),
                ],
                'types_id_locked' => [
                    'label' => __('tipo'),
                    'title' => __('tipo'),
                ],
            ],
        ];

        $assetTypes = self::getSupportedAssetTypes('lockfields');

        foreach ($assetTypes as $row) {
            $itemtype = $row['itemtype'];
            $subColumns = [];

            foreach (array_keys($matrix['columns']) as $column) {
                $subColumns[$column] = [
                    'checked'  => (int) ($row[$column] ?? 0),
                    'readonly' => !self::canUpdate(),
                ];
            }

            $matrix['rows'][$itemtype] = [
                'label'   => self::getItemtypeLabel($itemtype),
                'columns' => $subColumns,
            ];
        }

        return $matrix;
    }

    /**
     * Muestra el formulario de configuración de campos bloqueados.
     *
     * @return bool
     */
    public static function showConfigFieldForm(): bool
    {
        $matrix = self::getMatrixAssetFields();
        $rows = $matrix['rows'];
        $columns = $matrix['columns'];

        echo '<table class="tab_cadre_fixe"><tbody>';
        echo '<tr><th>' . __('Configuración de bloqueo de campos de activos', 'lockassetfield') . '</th></tr>';
        echo '<tr><td>' . __('Marque las casillas correspondientes para indicar qué campos deben permanecer bloqueados en los formularios de cada activo.', 'lockassetfield') . '</td></tr>';
        echo '</tbody></table>';

        echo '<div class="card">';
        echo '<div class="spaced p-3">';
        echo '<form method="post" action="' . self::getFormURL() . '">';

        Html::showCheckboxMatrix(
            $columns,
            $rows,
            [
                'title'         => __('Activos'),
                'row_check_all' => count($columns) > 1,
                'col_check_all' => count($rows) > 1,
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
        echo '</div>';
        echo '</div>';

        return true;
    }

    /**
     * Muestra el formulario de configuración de bloqueo por estado.
     *
     * @return void
     */
    public static function showConfigFieldStateForm(): void
    {
        $fieldStatesTypes = self::getSupportedAssetTypes();
        $elements = self::getStates();

        echo '<table class="tab_cadre_fixe"><tbody>';
        echo '<tr><th>' . __('Configuración de bloqueo de campo cambio de estado', 'lockassetfield') . '</th></tr>';
        echo '<tr><td>' . __('Seleccione en qué estados del dispositivo el campo Cambiar estado debe permanecer bloqueado.', 'lockassetfield') . '</td></tr>';
        echo '</tbody></table>';

        echo '<div class="card">';
        echo '<div class="spaced p-3">';
        echo '<div class="field-container">';
        echo '<form method="post" action="' . self::getFormURL() . '">';
        echo '<table class="tab_cadre_fixe">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Activo</th>';
        echo '<th>Estado</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($fieldStatesTypes as $fieldStatesType) {
            $configFieldState = new self();

            if (!$configFieldState->getFromDBByCrit(['itemtype' => $fieldStatesType])) {
                continue;
            }

            $nameInputHidden = $fieldStatesType . '[id]';
            $nameInputState = $fieldStatesType . '[state_ids]';

            echo '<input type="hidden" name="' . htmlspecialchars($nameInputHidden, ENT_QUOTES, 'UTF-8') . '" value="' . (int) $configFieldState->fields['id'] . '">';
            echo '<tr>';
            echo '<td class="tab_bg_1" style="width:40%">' . htmlspecialchars(self::getItemtypeLabel($fieldStatesType), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td style="width:60%">';

            Dropdown::showFromArray(
                $nameInputState,
                $elements,
                [
                    'multiple' => true,
                    'values'   => $configFieldState->fields['state_ids'] !== null
                        ? json_decode($configFieldState->fields['state_ids'], true)
                        : [],
                    'rand'     => mt_rand(),
                    'comments' => __('Seleccione uno o varios estados'),
                    'display'  => true,
                    'required' => false,
                    'width'    => '100%',
                    'readonly' => !self::canUpdate(),
                ]
            );

            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

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
        echo '</div>';
        echo '</div>';
    }

    /**
     * Devuelve los campos bloqueados para un itemtype.
     *
     * @param string $itemtype Nombre de clase del item.
     *
     * @return array<int, string>
     */
    public static function getLockedFieldsForItemType($itemtype): array
    {
        global $DB;

        $fields = [];

        $iterator = $DB->request([
            'SELECT' => [
                'itemtype',
                'serial_locked',
                'otherserial_locked',
                'manufacturers_id_locked',
                'models_id_locked',
                'types_id_locked',
            ],
            'FROM'  => self::$table,
            'WHERE' => [
                'itemtype' => $itemtype,
            ],
        ]);

        foreach ($iterator as $row) {
            foreach ($row as $col => $val) {
                if ($val !== 1 && $val !== '1') {
                    continue;
                }

                $field = str_replace('_locked', '', $col);

                if ($field === 'itemtype') {
                    continue;
                }

                if ($field === 'models_id') {
                    $item = getItemForItemtype($itemtype);

                    if ($item !== false && method_exists($item, 'getModelClass')) {
                        $modelClass = $item->getModelClass();

                        if ($modelClass !== null && class_exists($modelClass)) {
                            if (method_exists($modelClass, 'getForeignKeyField')) {
                                $fields[] = $modelClass::getForeignKeyField();
                            } else {
                                $itemtypeColumn = str_replace('glpi_', '', getTableForItemType($modelClass));
                                $fields[] = $itemtypeColumn . '_id';
                            }
                        }
                    }

                    continue;
                }

                if ($field === 'types_id') {
                    $item = getItemForItemtype($itemtype);

                    if ($item !== false && method_exists($item, 'getTypeClass')) {
                        $typeClass = $item->getTypeClass();

                        if ($typeClass !== null && class_exists($typeClass)) {
                            if (method_exists($typeClass, 'getForeignKeyField')) {
                                $fields[] = $typeClass::getForeignKeyField();
                            } else {
                                $itemtypeColumn = str_replace('glpi_', '', getTableForItemType($typeClass));
                                $fields[] = $itemtypeColumn . '_id';
                            }
                        }
                    }

                    continue;
                }

                $fields[] = $field;
            }
        }

        return array_values(array_unique($fields));
    }

    /**
     * Indica si el campo states_id debe bloquearse para un itemtype y estado.
     *
     * @param string $itemtype Nombre del itemtype.
     * @param int    $statesId ID del estado.
     *
     * @return array|bool
     */
    public static function isFieldStateLocked($itemtype, $statesId): array|bool
    {
        global $DB;

        $stateIds = [];

        $iterator = $DB->request([
            'SELECT' => ['state_ids'],
            'FROM'   => self::$table,
            'WHERE'  => [
                'itemtype' => $itemtype,
            ],
            'LIMIT'  => 1,
        ]);

        foreach ($iterator as $row) {
            if (!empty($row['state_ids'])) {
                $decoded = json_decode($row['state_ids'], true);

                if (is_array($decoded)) {
                    $stateIds = $decoded;
                }
            }
        }

        if (empty($stateIds)) {
            return false;
        }

        return in_array((int) $statesId, array_map('intval', $stateIds), true)
            ? ['states_id']
            : false;
    }

    /**
     * Devuelve los estados de GLPI.
     *
     * @return array<int, string>
     */
    public static function getStates(): array
    {
        global $DB;

        $elements = [];

        $glpiStates = $DB->request([
            'SELECT' => ['id', 'name'],
            'FROM'   => 'glpi_states',
            'ORDER'  => 'name',
        ]);

        foreach ($glpiStates as $data) {
            $elements[(int) $data['id']] = $data['name'];
        }

        return $elements;
    }

    /**
     * Comprueba si un itemtype existe en la tabla de configuración.
     *
     * @param string $itemtype Itemtype.
     *
     * @return bool
     */
    public static function existInConfigField($itemtype): bool
    {
        $configfield = new self();

        return $configfield->getFromDBByCrit(['itemtype' => $itemtype]);
    }

    /**
     * Instalación de la tabla de configuración de campos.
     *
     * @return void
     */
    public static function install(): void
    {
        global $DB;

        $table = getTableForItemType(__CLASS__);

        if (!$DB->tableExists($table)) {
            $query = "CREATE TABLE IF NOT EXISTS `$table` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `plugin_lockassetfield_configs_id` INT UNSIGNED NOT NULL DEFAULT 1,
                `itemtype` VARCHAR(255) NOT NULL DEFAULT '',
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
        } else {
            $query = "ALTER TABLE `$table` MODIFY `itemtype` VARCHAR(255) NOT NULL DEFAULT ''";
            $DB->query($query);
        }

        foreach (Config::lockAssetFieldType() as $type) {
            if (!countElementsInTable($table, ['itemtype' => $type])) {
                $field = new self();
                $field->add([
                    'plugin_lockassetfield_configs_id' => 1,
                    'itemtype'                         => $type,
                ]);
            }
        }

        self::syncSupportedAssetTypes();
    }

    /**
     * Desinstala la tabla de configuración de campos.
     *
     * @return void
     */
    public static function uninstall(): void
    {
        global $DB;

        $DB->dropTable(getTableForItemType(__CLASS__));
    }

    /**
     * Migra itemtypes heredados de GenericObject a custom assets de GLPI 11.
     *
     * @return void
     */
    private static function migrateLegacyGenericObjectItemtypes(): void
    {
        global $DB;

        $table = getTableForItemType(__CLASS__);

        foreach (ConfigAssetObject::getCustomAssetDefinitions() as $definition) {
            $systemName = $definition['system_name'];
            $newItemtype = $definition['itemtype'];

            if ($systemName === '' || $newItemtype === '') {
                continue;
            }

            foreach (self::getLegacyGenericObjectItemtypesForSystemName($systemName) as $legacyItemtype) {
                $legacy = new self();

                if (!$legacy->getFromDBByCrit(['itemtype' => $legacyItemtype])) {
                    continue;
                }

                if (countElementsInTable($table, ['itemtype' => $newItemtype])) {
                    $DB->delete(
                        $table,
                        [
                            'id' => $legacy->fields['id'],
                        ]
                    );

                    continue;
                }

                $legacy->update([
                    'id'       => $legacy->fields['id'],
                    'itemtype' => $newItemtype,
                ]);
            }
        }
    }

    /**
     * Construye posibles itemtypes heredados de GenericObject a partir del system_name.
     *
     * @param string $systemName Nombre técnico del activo.
     *
     * @return array<int, string>
     */
    private static function getLegacyGenericObjectItemtypesForSystemName(string $systemName): array
    {
        $normalized = ucfirst($systemName);
        $camelized = str_replace('_', '', ucwords($systemName, '_'));

        return array_values(
            array_unique([
                'PluginGenericobject' . $normalized,
                'PluginGenericobject' . $camelized,
                $normalized,
                $camelized,
            ])
        );
    }

    /**
     * Devuelve la etiqueta visible de un itemtype.
     *
     * @param string $itemtype Itemtype.
     *
     * @return string
     */
    private static function getItemtypeLabel(string $itemtype): string
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
}