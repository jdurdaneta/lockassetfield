# LockAssetField – Plugin para GLPI  
### Bloqueo avanzado de campos de activos y control dinámico por estado

**LockAssetField** es un plugin para **GLPI 10.x** que permite **bloquear la edición de campos sensibles en los activos del inventario**, ofreciendo un control avanzado que garantiza la integridad de la información.

## ✨ Características principales

### 🔒 Bloqueo de campos en activos
Permite definir qué campos de cada tipo de activo deben permanecer bloqueados:

- Número de serie (`serial`)
- Número de inventario (`otherserial`)
- Fabricante (`manufacturers_id`)
- Modelo (`models_id`)
- Tipo (`types_id`)
- Estado (`states_id` — bajo condiciones configurables)

### 📌 Bloqueo condicionado por estado
Puedes configurar, por tipo de activo:

- **Listas de estados** en los cuales el campo *Estado* queda bloqueado.
- El bloqueo se aplica tanto en la interfaz como en la validación previa al guardado.

### 🧩 Integración con GenericObject
Si el plugin **GenericObject** está activo:

- LockAssetField detecta automáticamente los tipos de objetos personalizados creados.
- Permite activarlos para aplicar bloqueo de campos.
- Gestiona su configuración del mismo modo que los activos nativos (Computer, Monitor, Printer, etc.).

### 🖥️ Interfaz de configuración integrada
El plugin añade pestañas dentro de la página de configuración:

1. **Configuración general**
2. **Bloqueo de campos**
3. **Cambio de estado**
4. **Gestión de objetos** (solo si GenericObject está activo)

Cada pestaña está completamente integrada en el sistema de formularios de GLPI 10 (Twig + TemplateRenderer).

### 🚫 Control estricto en pre_item_update
Antes de actualizar un activo:

- Detecta qué campos fueron modificados.
- Si alguno está bloqueado:
  - Se cancela la edición.
  - Se restaura el valor original.
  - Se informa al usuario mediante un mensaje de aviso.

### 🧩 Bloqueo en la vista del formulario
Los campos bloqueados se muestran:

- Deshabilitados (`disabled`)
- Con estilo visual atenuado
- Con tooltip explicativo

---

## 📦 Requisitos

| Componente | Versión |
|-----------|---------|
| GLPI | >= 10.0.0 y < 10.0.99 |
| PHP | 8.x |
| Base de datos | MySQL/MariaDB (según requisitos de GLPI) |
| GenericObject (opcional) | Compatible con integración automática |

---

## 🔧 Instalación

1. Copiar el plugin dentro de: `glpi/plugins/lockassetfield/`
2. Asignar permisos al usuario del servidor web: `chown -R www-data:www-data glpi/plugins/lockassetfield`
3. Ingresar a GLPI como super-admin  
→ **Configuración** → **Plugins**  
→ Instalar y activar **LockAssetField**

---

## ⚙️ Configuración del plugin

### 1. Configuración general
Permite activar/desactivar la funcionalidad del plugin y añadir notas internas.

### 2. Bloqueo de campos
Presenta una **matriz de campos por tipo de activo** donde puedes marcar qué campos se bloquearán.

### 3. Cambio de estado
Permite seleccionar **estados específicos** en los cuales el campo *Estado* queda protegido automáticamente.

### 4. Gestión de objetos (GenericObject)
Si el plugin está activo:

- Permite seleccionar qué tipos de objetos personalizados se gestionarán.
- Se integran en las matrices de bloqueo como cualquier otro activo GLPI.

---

## 🔐 Permisos

El plugin define un derecho propio:

| Derecho | Función |
|---------|----------|
| `plugin_lockassetfield_config` | Acceso a la configuración del plugin |

Y añade un derecho interno:

| Constante | Uso |
|-----------|------|
| `RIGHT_UPDATE_FIELDS` | Permite editar campos aunque estén bloqueados |

Los administradores globales (`config` + `update`) pueden ignorar bloqueos.

---

## 🧩 Hooks utilizados

| Hook | Uso |
|------|-----|
| `pre_item_update` | Previene cambios no autorizados en campos bloqueados |
| `post_show_item` | Añade JavaScript para bloquear campos visualmente |

---

## 📝 Tablas de base de datos creadas

- `glpi_plugin_lockassetfield_configs`
- `glpi_plugin_lockassetfield_configfields`

Todas gestionadas mediante clases `CommonDBTM`.

---

## 🛠️ Desarrollo

### Estándares utilizados
- **PSR-12**
- Estructura oficial de plugins GLPI
- Twig para plantillas
- TemplateRenderer de GLPI 10

### Recomendaciones para desarrolladores
- No modificar las tablas directamente
- Usar siempre métodos de `CommonDBTM` (`getFromDB`, `update`, `add`, etc.)
- Mantener coherencia con `ConfigField::lockAssetFieldType()`

---

## ❗ Limitaciones conocidas

- Los campos bloqueados deben existir en cada itemtype.
- Para objetos de GenericObject, es necesario activarlos en la pestaña “Gestión de objetos”.
- La validación se realiza tanto en formulario como en backend.

---

## 🤝 Contribuciones

Las contribuciones son bienvenidas:  
issues, mejoras y pull requests en el repositorio oficial.

Repositorio:  
👉 https://github.com/pluginsGLPI/lockassetfield *(o tu URL real)*

---

## 📄 Licencia

**GPLv2+**  
Puedes usar, modificar y redistribuir el plugin según los términos de la licencia.

---

## 👨‍💻 Autores

**Equipo INTEF – Ministerio de Educación**  
Desarrollo del plugin **LockAssetField**

