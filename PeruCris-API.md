# API PerúCRIS — Guía Completa para Implementación
> **Fuentes:** Directrices #PerúCRIS v1.1 (CONCYTEC, Junio 2024) + Guía de APIs JSON (CONCYTEC, Marzo 2026)
> **Protocolo:** OAI-PMH 2.0 | **Formato:** CERIF JSON | **Namespace:** `https://purl.org/pe-repo/perucris/cerif`
> **metadataPrefix:** `perucris-cerif` | **Base:** CERIF 1.5 + OpenAIRE Guidelines v1.1.1

---

## Índice

1. [Estándares e Identificadores](#1-estándares-e-identificadores)
2. [Niveles de Persistencia y Ocurrencia](#2-niveles-de-persistencia-y-ocurrencia)
3. [Protocolo OAI-PMH](#3-protocolo-oai-pmh)
4. [Estructura base de los JSON](#4-estructura-base-de-los-json)
5. [Entidades — Tablas de campos con descripción y salida JSON](#5-entidades--tablas-de-campos-con-descripción-y-salida-json)
   - [5.1 Person — Persona](#51-person--persona)
   - [5.2 OrgUnit — Unidad Organizativa](#52-orgunit--unidad-organizativa)
   - [5.3 Funding — Financiamiento](#53-funding--financiamiento)
   - [5.4 Project — Proyecto](#54-project--proyecto)
   - [5.5 Equipment — Equipamiento](#55-equipment--equipamiento)
   - [5.6 Publication — Publicación](#56-publication--publicación)
   - [5.7 Patent — Patente](#57-patent--patente)
   - [5.8 Product — Producto de Investigación](#58-product--producto-de-investigación)
6. [Verbos OAI-PMH y respuestas JSON](#6-verbos-oai-pmh-y-respuestas-json)
7. [URIs y vocabularios controlados](#7-uris-y-vocabularios-controlados)

---

## 1. Estándares e Identificadores

| Ámbito | Estándar / Vocabulario |
|--------|------------------------|
| Formato de intercambio | CERIF-XML / JSON |
| Protocolo de cosecha | OAI-PMH 2.0 |
| Fechas | ISO 8601 — `AAAA-MM-DD`, `AAAA-MM`, `AAAA` |
| Idiomas | IETF language tag — ISO 639-1 (2 caracteres, ej. `es`, `en`) |
| Países | ISO 3166 (2 caracteres, ej. `PE`, `US`) |
| Monedas | ISO 4217 (3 caracteres, ej. `PEN`, `USD`) |
| Tipos de recursos | COAR Controlled Vocabulary v3.0 |
| Derechos de acceso | COAR Access Rights v1.0 |
| Versiones | COAR Version Types v1.0 |
| Campos OCDE | `https://purl.org/pe-repo/ocde/ford` |
| Actividades CTI | `https://purl.org/pe-repo/ocde/tipoProyecto` |
| Clasificación industrial | CIIU (SUNAT) |
| Patentes | CIP — `http://data.epo.org/linked-data/def/ipc/` |
| Geo Perú | UbiGeo INEI |
| Trabajos académicos | RENATI — `https://purl.org/pe-repo/renati/type` |
| Personas | ORCID, DNI, Scopus Author ID, ResearcherID (WoS) |
| Organizaciones | ROR, ISNI, GRID, RUC, Scopus Affiliation, CrossRef Funder |
| Publicaciones | DOI, Handle, ARK, ISBN, ISSN, PMCID, ISI-Number, SCP-Number |

---

## 2. Niveles de Persistencia y Ocurrencia

### Persistencia

| Nivel | Abrev. | Descripción |
|-------|--------|-------------|
| **Obligatorio** | `M` | Siempre presente. No se permite vacío. |
| **Obligatorio si aplica** | `MA` | Presente cuando se cumplen condiciones previas definidas. |
| **Recomendado** | `R` | No obligatorio pero exhortado para calidad del dato. |
| **Opcional** | `O` | Puede estar o no. Información complementaria. |

### Ocurrencia → representación JSON

| Nivel | JSON |
|-------|------|
| **No repetible** | Valor escalar `"string"` u objeto `{}` |
| **Repetible** | Array `[]` — puede tener uno o más elementos |

---

## 3. Protocolo OAI-PMH

**URL base:** `https://cris.institucion.edu.pe/oai`

### Sets disponibles

| `setSpec` | Entidad |
|-----------|---------|
| `persons` | Personas |
| `orgunits` | Unidades organizativas |
| `publications` | Publicaciones |
| `projects` | Proyectos |
| `fundings` | Financiamientos |
| `equipments` | Equipamientos |
| `patents` | Patentes |

### Tipos de cosecha

| Tipo | Cuándo usar | Request |
|------|-------------|---------|
| **Completa** | Primera carga / sin checkpoint | `?verb=ListRecords&metadataPrefix=perucris-cerif&set=projects` |
| **Incremental** | Solo nuevos/modificados | `?verb=ListRecords&metadataPrefix=perucris-cerif&set=projects&from=2026-02-25T00:00:00Z&until=2026-03-05T23:59:59Z` |
| **Paginada** | Cuando server devuelve token | `?verb=ListRecords&resumptionToken=eyJwYWdlIjoyfQ==` |

### Regla del `resumptionToken`

- `#text` no vacío → hay más páginas → repetir solo con token
- `#text` vacío o ausente → fin de cosecha
- Al continuar con token **NO agregar** `from`/`until`

> **Convención JSON:** atributos XML → prefijo `@` | texto del nodo → `#text`

---

## 4. Estructura base de los JSON

```json
{
  "id": "EntityType/123",
  "title": [
    { "lang": "es", "value": "Título en español" },
    { "lang": "en", "value": "Title in English" }
  ],
  "identifiers": [
    { "scheme": "URI_vocabulario", "value": "valor_identificador" }
  ],
  "relations": [
    { "type": "tipo_relacion", "target": { "id": "EntityType/456" } }
  ],
  "lastModified": "2026-02-20T12:00:00Z"
}
```

**Reglas obligatorias:**
- `id` siempre con prefijo de entidad: `Persons/…`, `OrgUnits/…`, `Projects/…`, `Publications/…`, `Fundings/…`, `Patents/…`, `Equipments/…`
- Campos multilenguaje: siempre array `[{ "lang": "es", "value": "..." }]`
- `lastModified` en ISO 8601 — requerido para cosecha incremental
- No usar elementos o vocabularios distintos a los definidos en el perfil

---

## 5. Entidades — Tablas de campos con descripción y salida JSON

---

### 5.1 Person — Persona

**Definición:** Investigador, innovador u otra persona vinculada a actividades de CTI.
**CERIF:** `https://w3id.org/cerif/model#Person` | **Set:** `persons`

| ID | Campo JSON | Descripción | Formato / Valores | Persist. | Ocurrencia |
|----|-----------|-------------|-------------------|----------|------------|
| 7.2.4.1 | `id` | Código alfanumérico generado por el sistema de origen. No debe contener espacios ni caracteres internacionales. | `"Persons/{id}"` — alfanumérico sin espacios | M | No repetible |
| 7.2.4.2 | `personName` | Nombre completo de la persona. | Objeto con subcampos | M | No repetible |
| 7.2.4.2.1 | `personName.familyNames` | Apellidos de la persona. | Texto libre | O | No repetible |
| 7.2.4.2.2 | `personName.firstNames` | Nombres de pila de la persona. | Texto libre | O | No repetible |
| — | `personName.fullName` | Nombre completo concatenado. | Texto libre | O | No repetible |
| 7.2.4.3 | `gender` | Género de la persona. En PerúCRIS se obtiene automáticamente de RENIEC por DNI. Si no se puede determinar, omitir. | `"m"` = masculino / `"f"` = femenino | M | No repetible |
| 7.2.4.4 | `affiliations` | Organización o unidad organizativa a la que está afiliada la persona. Incluye fecha de inicio y fin. Embebe objeto OrgUnit con al menos `id` y `name`. | Array de objetos con `orgUnit`, `role`, `startDate`, `endDate` | R | Repetible |
| 7.2.4.5 | `emails` | Página web personal, correo electrónico y/o teléfonos. Usar prefijos: `https://` para web, `mailto:` para email, `tel:` para teléfono. | Array de strings con prefijo de protocolo | R | Repetible |
| 7.2.4.6 | `identifiers` (DNI) | Número del DNI registrado en RENIEC. Solo para investigadores peruanos. 8 dígitos numéricos. | Scheme: `http://purl.org/pe-repo/concytec/terminos#dni` | MA | No repetible |
| 7.2.4.7 | `identifiers` (ORCID) | Identificador ORCID principal de la persona. Usar cuando el valor sea seguro o sea el preferido. URL completa. | Scheme: `https://orcid.org` | MA | No repetible |
| 7.2.4.8 | `identifiers` (ScopusAuthorID) | Identificador principal de autor en Scopus. 10–11 dígitos numéricos. Si hay varios sin principal definido, usar campo alternativo. | Scheme: `https://w3id.org/cerif/vocab/IdentifierTypes#ScopusAuthorID` | R | No repetible |
| 7.2.4.9 | `identifiers` (AlternativeScopusAuthorID) | Otros identificadores Scopus no fusionados con el principal. 10–11 dígitos. | Scheme: `https://w3id.org/cerif/vocab/IdentifierTypes#ScopusAuthorID` | O | Repetible |
| 7.2.4.10 | `identifiers` (ResearcherID) | Identificador de autor en Web of Science (Thomson Reuters). | Scheme: `https://w3id.org/cerif/vocab/IdentifierTypes#ResearcherID` | O | No repetible |
| — | `keywords` | Palabras clave sobre áreas de investigación de la persona. | Array `[{ "value": "..." }]` | O | Repetible |
| — | `lastModified` | Fecha y hora de última modificación. Requerido para cosecha incremental. | ISO 8601 `AAAA-MM-DDTHH:MM:SSZ` | M | No repetible |

**Salida JSON completa:**

```json
{
  "id": "Persons/28521427",
  "personName": {
    "familyNames": "Cabrera Sánchez",
    "firstNames": "Paolo Edgar",
    "fullName": "Paolo Edgar Cabrera Sánchez"
  },
  "gender": "m",
  "identifiers": [
    { "scheme": "http://purl.org/pe-repo/concytec/terminos#dni", "value": "08755499" },
    { "scheme": "https://orcid.org", "value": "https://orcid.org/0000-0001-8247-8975" },
    { "scheme": "https://w3id.org/cerif/vocab/IdentifierTypes#ResearcherID", "value": "HRC-7754-2023" },
    { "scheme": "https://w3id.org/cerif/vocab/IdentifierTypes#ScopusAuthorID", "value": "57123456789" }
  ],
  "emails": [
    "mailto:paolo.cabrera@unica.edu.pe",
    "https://investigadores.unica.edu.pe/pcabrera",
    "tel:+51014249875"
  ],
  "affiliations": [
    {
      "orgUnit": { "id": "OrgUnits/38498322", "name": "Universidad Nacional San Luis Gonzaga" },
      "role": "Autor",
      "startDate": "2020-01-01"
    }
  ],
  "keywords": [{ "value": "Ciencias veterinarias" }],
  "lastModified": "2026-02-20T12:00:00Z"
}
```

---

### 5.2 OrgUnit — Unidad Organizativa

**Definición:** Institución o unidad organizativa dedicada a actividades de CTI.
**CERIF:** `https://w3id.org/cerif/model#OrgUnit` | **Set:** `orgunits`

| ID | Campo JSON | Descripción | Formato / Valores | Persist. | Ocurrencia |
|----|-----------|-------------|-------------------|----------|------------|
| 7.2.3.1 | `id` | Código generado por el sistema de origen. Sin espacios ni caracteres internacionales. | `"OrgUnits/{id}"` — alfanumérico | M | No repetible |
| 7.2.3.2 | `name` | Nombre oficial de la unidad organizativa. Repetible para diferentes idiomas con atributo `lang`. | Array `[{ "lang": "es", "value": "..." }]` | M | Repetible |
| 7.2.3.3 | `acronym` | Sigla o acrónimo de la institución. | Texto libre, ej. `"UNMSM"` | O | No repetible |
| 7.2.3.4 | `address` | Domicilio fiscal o principal de la unidad. | Objeto con subcampos | O | No repetible |
| 7.2.3.4.1 | `address.street` | Calle, número y urbanización. | Texto libre | O | No repetible |
| 7.2.3.4.2 | `address.addressLine` | Distrito. | Texto libre | O | No repetible |
| 7.2.3.4.3 | `address.city` | Provincia. | Texto libre | O | No repetible |
| 7.2.3.4.4 | `address.region` | Departamento. | Texto libre | O | No repetible |
| 7.2.3.4.5 | `address.postalCode` | Código postal. | Texto numérico | O | No repetible |
| 7.2.3.4.6 | `address.countryCode` | País. | ISO 3166 — 2 caracteres: `"PE"` | O | No repetible |
| 7.2.3.5 | `ubiGeo` | Código de ubicación geográfica INEI. Aplica para instituciones peruanas. | Código UbiGeo, ej. `"150101"` (Lima) | MA | No repetible |
| 7.2.3.6 | `identifiers` (RUC) | RUC asignado por SUNAT. Para instituciones peruanas o extranjeras con representación en Perú. 11 dígitos. | Scheme: `https://purl.org/pe-repo/concytec/terminos#ruc` | MA | No repetible |
| 7.2.3.7 | `identifiers` (ROR) | Identificador abierto y persistente para organizaciones de investigación asignado por ROR. | Scheme: `https://ror.org` — URL completa | MA | No repetible |
| 7.2.3.8 | `identifiers` (ISNI) | Número estándar internacional para actores en trabajos creativos. 16 dígitos. | Scheme: `https://isni.org` | MA | No repetible |
| 7.2.3.9 | `identifiers` (Scopus) | Código de identificación en Scopus (Elsevier). | Scheme: `https://www.scopus.com` | MA | No repetible |
| 7.2.3.10 | `identifiers` (GRID) | Identificador GRID de la organización. | Scheme: `https://www.grid.ac` | O | No repetible |
| 7.2.3.11 | `identifiers` (CrossRef) | Identificador para instituciones financiadoras en CrossRef Funder Registry. | URL DOI | O | No repetible |
| 7.2.3.12 | `sectorInstitucional` | Sector institucional según Manual de Frascati (OCDE). Clasifica en uno de los 5 grandes sectores. | URI vocabulario CONCYTEC | MA | No repetible |
| 7.2.3.13 | `type` | Tipo de organización según vocabulario CONCYTEC. | URI: `https://purl.org/pe-repo/concytec/tipoOrganizacion` | M | No repetible |
| 7.2.3.14 | `partOf` | Unidad organizativa padre en la jerarquía. Permite construir árbol UNMSM > Facultad > Instituto > Grupo. La raíz NO lleva este campo. | Objeto `{ "orgUnit": { "id": "OrgUnits/..." } }` | MA | Repetible |
| 7.2.3.15 | `dependencyType` | Tipo de dependencia respecto a la unidad padre. | URI vocabulario CONCYTEC | MA | No repetible |
| 7.2.3.16 | `higherEducationType` | Tipo de institución de enseñanza superior según SUNEDU. | URI vocabulario SUNEDU-CONCYTEC | MA | No repetible |
| 7.2.3.17 | `ciiu` | Clasificación Industrial Internacional Uniforme según SUNAT. | Código CIIU, ej. `"8542"` | O | Repetible |
| 7.2.3.18 | `ocdeFields` | Campo del conocimiento OCDE para el que trabaja la unidad. | URI `https://purl.org/pe-repo/ocde/ford` | MA | Repetible |
| 7.2.3.19 | `nature` | Naturaleza pública o privada de la institución. | URI vocabulario CONCYTEC | MA | No repetible |
| 7.2.3.20 | `keywords` | Palabras clave sobre actividades de la unidad. | Array `[{ "value": "..." }]` | R | Repetible |
| 7.2.3.21–22 | `websites` | Páginas web y correos. Tipos: `homepage`, `cris`, `email`. | Array `[{ "type": "homepage", "url": "https://..." }]` | R | Repetible |
| 7.2.3.23 | `description` | Descripción general de la unidad. | Array `[{ "lang": "es", "value": "..." }]` | R | Repetible |
| — | `classifications` | Clasificaciones adicionales con vocabularios controlados. | Array `[{ "scheme": "URI", "value": "valor" }]` | O | Repetible |
| — | `lastModified` | Fecha de última modificación. | ISO 8601 | M | No repetible |

**Salida JSON — Jerarquía completa:**

```json
{
  "id": "OrgUnits/1",
  "acronym": "UNMSM",
  "name": [
    { "lang": "es", "value": "Universidad Nacional Mayor de San Marcos" },
    { "lang": "en", "value": "National University of San Marcos" }
  ],
  "type": "Institución principal",
  "identifiers": [
    { "scheme": "https://purl.org/pe-repo/concytec/terminos#ruc", "value": "20148092282" },
    { "scheme": "https://ror.org", "value": "https://ror.org/040zs9j54" },
    { "scheme": "https://www.grid.ac", "value": "grid.11301.36" },
    { "scheme": "https://isni.org", "value": "0000000121662407" }
  ],
  "countryCode": "PE",
  "ubiGeo": "150101",
  "address": {
    "street": "Calle Germán Amézaga 375",
    "addressLine": "Lima Cercado",
    "city": "Lima",
    "region": "Lima",
    "postalCode": "15081",
    "countryCode": "PE"
  },
  "websites": [
    { "type": "homepage", "url": "https://www.unmsm.edu.pe" },
    { "type": "cris",     "url": "https://rais.unmsm.edu.pe" }
  ],
  "classifications": [
    { "scheme": "https://purl.org/pe-repo/concytec/tipoOrganizacion", "value": "Institución principal" }
  ],
  "lastModified": "2026-02-20T12:00:00Z"
}
```

```json
{
  "id": "OrgUnits/F10",
  "name": [{ "lang": "es", "value": "Facultad de Medicina San Fernando" }],
  "type": "Facultad",
  "partOf": { "orgUnit": { "id": "OrgUnits/1" } },
  "lastModified": "2026-02-20T12:00:00Z"
}
```

```json
{
  "id": "OrgUnits/I025",
  "name": [{ "lang": "es", "value": "Instituto de Patología" }],
  "type": "Instituto",
  "partOf": { "orgUnit": { "id": "OrgUnits/F10" } },
  "lastModified": "2026-02-20T12:00:00Z"
}
```

```json
{
  "id": "OrgUnits/G047",
  "name": [{ "lang": "es", "value": "Grupo de Investigación en Oncología" }],
  "type": "Grupo de investigación",
  "partOf": { "orgUnit": { "id": "OrgUnits/I025" } },
  "lastModified": "2026-02-20T12:00:00Z"
}
```

---

### 5.3 Funding — Financiamiento

**Definición:** Dotación de dinero destinada a la ejecución de actividades de I+D+i en sus diferentes niveles.
**CERIF:** `https://w3id.org/cerif/model#Funding` | **Set:** `fundings`

| ID | Campo JSON | Descripción | Formato / Valores | Persist. | Ocurrencia |
|----|-----------|-------------|-------------------|----------|------------|
| 7.2.1.1 | `id` | Código alfanumérico del sistema de origen. Sin espacios ni caracteres internacionales. | `"Fundings/{id}"` | M | No repetible |
| 7.2.1.2 | `type` | Nivel o modalidad del financiamiento. No alterar mayúsculas/minúsculas del URI. | URI OpenAIRE Funding Types. Ver tabla de valores abajo. | M | No repetible |
| 7.2.1.3 | `identifiers` (AwardNumber) | Código público del financiamiento tal como figura en documentos oficiales. Si no hay identificador persistente, usar `AwardNumber`. | Scheme: `https://w3id.org/cerif/vocab/IdentifierTypes#AwardNumber` | M | No repetible |
| 7.2.1.4 | `title` | Nombre oficial del financiamiento. Repetible para distintos idiomas. | Array `[{ "lang": "es", "value": "..." }]` | M | Repetible |
| 7.2.1.5 | `acronym` | Sigla o acrónimo del financiamiento. | Texto libre | O | No repetible |
| 7.2.1.6 | `fundedBy` | Entidad subvencionadora. Embebe OrgUnit con al menos `id` y `name`. | Objeto `{ "orgUnit": { "id": "...", "name": "..." } }` | M | No repetible |
| 7.2.1.7 | `partOf` | Financiamiento de nivel superior del que forma parte (ej. convocatoria dentro de un programa). | Objeto `{ "id": "Fundings/..." }` | MA | No repetible |
| 7.2.1.8 | `startDate` | Fecha de inicio del periodo del financiamiento. | ISO 8601: `AAAA-MM-DD`, `AAAA-MM` o `AAAA` | R | No repetible |
| 7.2.1.8 | `endDate` | Fecha de fin del periodo del financiamiento. | ISO 8601: `AAAA-MM-DD`, `AAAA-MM` o `AAAA` | R | No repetible |
| 7.2.1.9 | `amount` | Monto programado. Enteros sin decimales ni separadores. Moneda ISO 4217. | Objeto `{ "value": 250000, "currency": "PEN" }` | R | No repetible |
| 7.2.1.10 | `executedAmount` | Monto ejecutado al cierre. Mismo formato que `amount`. | Objeto `{ "value": 230000, "currency": "PEN" }` | R | No repetible |
| 7.2.1.11 | `description` | Descripción del financiamiento en distintos idiomas. | Array `[{ "lang": "es", "value": "..." }]` | R | Repetible |
| 7.2.1.12 | `keywords` | Palabras clave del financiamiento. | Array `[{ "value": "..." }]` | R | Repetible |
| 7.2.1.13 | `oaMandate` | Mandato de Acceso Abierto. Indica si existe obligación de publicar en acceso abierto. | Objeto con `mandated` y `uri` | R | Repetible |
| 7.2.1.13.1 | `oaMandate.mandated` | ¿Tiene mandato de acceso abierto? | `true` o `false` | M | No repetible |
| 7.2.1.13.2 | `oaMandate.uri` | URL del documento que establece el mandato. | URL | O | Repetible |
| — | `relatedProjects` | Proyectos financiados por este financiamiento. | Array: `["Projects/..."]` | O | Repetible |
| — | `url` | URL del financiamiento en el sistema de origen. | URL completa | O | No repetible |
| — | `lastModified` | Fecha de última modificación. | ISO 8601 | M | No repetible |

**Vocabulario `type`:**

| URI completa | Tipo | Ejemplo de uso |
|-------------|------|----------------|
| `…#FundingProgramme` | Programa de financiamiento | Proyectos de I+D, Fondo Marco, fondos por encargo |
| `…#Call` | Convocatoria | Concurso PNIPA 2018, Convocatoria SENCICO 2017-I |
| `…#Grant` | Subvención | Beca de doctorado, Pasantía |
| `…#Award` | Premio o reconocimiento | Premio a la Innovación Docente, Incentivo publicación Scopus Q1 |
| `…#Tender` | Financiamiento por licitación | Consultoría para estudios I+D |
| `…#Gift` | Donación | Donación empresarial para investigación COVID-19 |
| `…#InternalFunding` | Fondos propios | Presupuesto ordinario institucional, contrapartida |
| `…#Contract` | Otras modalidades | Contratos genéricos de financiamiento |

> Base URI: `https://www.openaire.eu/cerif-profile/vocab/OpenAIRE_Funding_Types`

**Salida JSON completa:**

```json
{
  "id": "Fundings/187597",
  "title": [{ "lang": "es", "value": "Contrato No. 08-2019-FONDECYT" }],
  "type": {
    "scheme": "https://www.openaire.eu/cerif-profile/vocab/OpenAIRE_Funding_Types",
    "value": "https://www.openaire.eu/cerif-profile/vocab/OpenAIRE_Funding_Types#Grant"
  },
  "identifiers": [
    { "scheme": "https://w3id.org/cerif/vocab/IdentifierTypes#AwardNumber", "value": "08-2019-FON-DECYT" },
    { "scheme": "https://w3id.org/cerif/vocab/IdentifierTypes#FundRefID", "value": "http://dx.doi.org/10.13039/501100010751" }
  ],
  "fundedBy": {
    "orgUnit": {
      "id": "OrgUnits/854622",
      "acronym": "FONDECYT",
      "name": "Fondo Nacional de Desarrollo Científico, Tecnológico y de Innovación Tecnológica"
    }
  },
  "startDate": "2019-01-01",
  "endDate": "2020-12-31",
  "amount": { "value": 250000, "currency": "PEN" },
  "executedAmount": { "value": 248500, "currency": "PEN" },
  "description": [{ "lang": "es", "value": "Financiamiento para investigación en epidemiología veterinaria." }],
  "keywords": [{ "value": "epidemiología" }, { "value": "veterinaria" }],
  "oaMandate": {
    "mandated": true,
    "uri": ["https://concytec.gob.pe/politica-acceso-abierto"]
  },
  "relatedProjects": ["Projects/358478"],
  "url": "https://funder.example.pe/awards/08-2019-FON-DECYT",
  "lastModified": "2026-02-22T09:10:00Z"
}
```

---

### 5.4 Project — Proyecto

**Definición:** Actividad en CTI con objetivos, plazos y presupuesto definidos.
**CERIF:** `https://w3id.org/cerif/model#Project` | **Set:** `projects`

| ID | Campo JSON | Descripción | Formato / Valores | Persist. | Ocurrencia |
|----|-----------|-------------|-------------------|----------|------------|
| 7.2.2.1 | `id` | Código del sistema de origen. Sin espacios ni caracteres internacionales. | `"Projects/{id}"` | M | No repetible |
| 7.2.2.2 | `type` (OCDE) | Tipo de actividad según Manual de Frascati y Oslo. Clasificar en segundo nivel. Puede repetirse. | URI `https://purl.org/pe-repo/ocde/tipoProyecto#…` | MA | Repetible |
| 7.2.2.3 | `type` (CTI) | Otros tipos CTI no contemplados por OCDE según vocabulario CONCYTEC. | URI `https://purl.org/pe-repo/concytec/terminos#…` | O | Repetible |
| 7.2.2.4 | `identifiers` | Código público del proyecto tal como aparece en documentos oficiales. | Scheme: `https://w3id.org/cerif/vocab/IdentifierTypes#ProjectReference` | M | No repetible |
| 7.2.2.5 | `title` | Título oficial del proyecto. Repetible para distintos idiomas. | Array `[{ "lang": "es", "value": "..." }]` | M | Repetible |
| 7.2.2.6 | `acronym` | Acrónimo del proyecto. | Texto libre, ej. `"SARNAVIC"` | O | No repetible |
| 7.2.2.7 | `startDate` | Fecha de inicio del proyecto. | ISO 8601: `AAAA-MM-DD` | R | No repetible |
| 7.2.2.8 | `endDate` | Fecha de fin del proyecto. | ISO 8601: `AAAA-MM-DD` | R | No repetible |
| 7.2.2.9 | `participants` | Personas y organizaciones que participan en el proyecto con su rol. Cada participante tiene `person` u `orgUnit` y un `role`. | Array de objetos con `person`/`orgUnit` + `role` | M | Repetible |
| 7.2.2.11 | `fundings` | Referencias a financiamientos de origen del proyecto. | Array `[{ "id": "Fundings/..." }]` | MA | Repetible |
| 7.2.2.12 | `subjects` (OCDE) | Campo del conocimiento según Frascati. Clasificar en segundo nivel. | Array `[{ "scheme": "https://purl.org/pe-repo/ocde/ford", "value": "URI" }]` | MA | Repetible |
| 7.2.2.13 | `subjects` (CIIU) | Clasificación Industrial CIIU (SUNAT). | Array `[{ "scheme": "ciiu", "value": "código" }]` | O | Repetible |
| 7.2.2.14 | `researchLine` | Línea de investigación institucional del proyecto. | Texto libre | R | Repetible |
| 7.2.2.15 | `geoLocations` | Ámbito geográfico de estudio. Puede ser punto (`pointLatitude`/`pointLongitude`), caja o lugar nombrado (`geoLocationPlace`). | Array de objetos geoLocation | MA | Repetible |
| 7.2.2.16 | `keywords` | Palabras clave. Repetible para distintos idiomas. | Array `[{ "lang": "es", "value": "..." }]` | R | Repetible |
| 7.2.2.17 | `abstract` | Resumen del proyecto. Repetible para distintos idiomas. | Array `[{ "lang": "es", "value": "..." }]` | R | Repetible |
| 7.2.2.18 | `status` | Estado del proyecto según vocabulario CONCYTEC. | `"completed"`, `"ongoing"`, `"cancelled"`, etc. | R | No repetible |
| 7.2.2.19 | `uses` | Equipamientos utilizados en el proyecto. | Array `["Equipments/..."]` | R | Repetible |
| 7.2.2.20 | `url` | URL del proyecto en el sistema de origen. | URL completa | R | Repetible |
| 7.2.2.21 | `oaMandate` | Mandato de Acceso Abierto del proyecto. | Objeto con `mandate` (boolean) y `uri` | R | Repetible |
| — | `outputs` | Salidas vinculadas: publicaciones, patentes, productos. | Objeto `{ "publications": [], "patents": [], "products": [] }` | O | No repetible |
| — | `lastModified` | Fecha de última modificación. | ISO 8601 | M | No repetible |

**Vocabulario `type` OCDE:**

| URI (sin base) | Tipo |
|---------------|------|
| `#investigacionBasica` | Investigación básica |
| `#investigacionAplicada` | Investigación aplicada |
| `#desarrolloExperimental` | Desarrollo experimental |
| `#innovacionTecnologica` | Innovación tecnológica |
| `#innovacionDeProducto` | Innovación de producto |
| `#innovacionDeProcesoNegocio` | Innovación de proceso de negocio |

> Base URI: `https://purl.org/pe-repo/ocde/tipoProyecto`

**Vocabulario `type` CTI CONCYTEC:**

| URI (sin base) | Tipo |
|---------------|------|
| `#desarrolloTecnologico` | Desarrollo tecnológico |
| `#transferenciaTecnologica` | Transferencia tecnológica |
| `#investigacionAdaptativa` | Investigación adaptativa |
| `#investigacionEconomica` | Investigación económica |
| `#serviciosDeExtension` | Servicios de extensión |
| `#fortalecimientoDeCapacidades` | Fortalecimiento de capacidades |
| `#incubacionYDesarrolloDeEmpresas` | Incubación y desarrollo de empresas |
| `#equipamientoCientifico` | Equipamiento científico |

> Base URI: `https://purl.org/pe-repo/concytec/terminos`

**Salida JSON completa:**

```json
{
  "id": "Projects/358478",
  "title": [
    { "lang": "es", "value": "Sarna en vicuñas: epidemiología y estrategias para su prevención y control" }
  ],
  "acronym": "SARNAVIC",
  "identifiers": [
    { "scheme": "https://w3id.org/cerif/vocab/IdentifierTypes#ProjectReference", "value": "160-2017" }
  ],
  "type": [
    {
      "scheme": "https://purl.org/pe-repo/ocde/tipoProyecto",
      "value": "https://purl.org/pe-repo/ocde/tipoProyecto#investigacionAplicada"
    }
  ],
  "startDate": "2017-01-01",
  "endDate": "2020-10-31",
  "status": "completed",
  "abstract": [{ "lang": "es", "value": "Proyecto de epidemiología veterinaria en camélidos sudamericanos." }],
  "keywords": [
    { "lang": "es", "value": "Vicuñas" },
    { "lang": "es", "value": "Epidemiología" }
  ],
  "subjects": [
    { "scheme": "https://purl.org/pe-repo/ocde/ford", "value": "https://purl.org/pe-repo/ocde/ford#1.06.05" }
  ],
  "participants": [
    {
      "person": { "id": "Persons/28521427", "name": "Paolo Edgar Cabrera Sánchez" },
      "role": "Investigador principal"
    },
    {
      "orgUnit": { "id": "OrgUnits/38498322", "name": "Universidad Nacional San Luis Gonzaga" },
      "role": "Institución ejecutora"
    }
  ],
  "fundings": [{ "id": "Fundings/187597" }],
  "outputs": {
    "publications": ["Publications/894518"],
    "patents": [],
    "products": []
  },
  "url": "https://cris.institucion.edu.pe/projects/358478",
  "lastModified": "2026-02-22T09:10:00Z"
}
```

---

### 5.5 Equipment — Equipamiento

**Definición:** Equipamiento científico o tecnológico disponible para actividades de I+D+i.
**CERIF:** `https://w3id.org/cerif/model#Equipment` | **Set:** `equipments`

| ID | Campo JSON | Descripción | Formato / Valores | Persist. | Ocurrencia |
|----|-----------|-------------|-------------------|----------|------------|
| 7.2.5.1 | `id` | Código del sistema de origen. Sin espacios ni caracteres internacionales. | `"Equipments/{id}"` | M | No repetible |
| 7.2.5.2 | `identifiers` (CRISID) | Código institucional del equipamiento asignado por la institución propietaria. | Scheme: `https://w3id.org/cerif/vocab/IdentifierTypes#CRISID` | M | No repetible |
| 7.2.5.2 | `identifiers` (SerialNumber) | Número de serie del fabricante. | Scheme: `https://w3id.org/cerif/vocab/IdentifierTypes#SerialNumber` | O | No repetible |
| 7.2.5.3 | `type` | Tipo de equipamiento según vocabulario CONCYTEC para CTI. | URI vocabulario tipos de equipamiento CONCYTEC | M | No repetible |
| 7.2.5.4 | `name` | Nombre oficial del equipamiento. Repetible para distintos idiomas. | Array `[{ "lang": "es", "value": "..." }]` | M | Repetible |
| 7.2.5.5 | `acronym` | Acrónimo o nombre corto. | Texto libre | O | No repetible |
| 7.2.5.6 | `owner` | Unidad organizativa propietaria. Embebe OrgUnit con al menos `id` y `name`. | Objeto `{ "orgUnit": { "id": "...", "name": "..." } }` | M | Repetible |
| 7.2.5.7 | `usage` | Tipo de uso en actividades CTI (uso propio, compartido, servicio externo, etc.). | URI vocabulario uso equipamiento CONCYTEC | R | Repetible |
| 7.2.5.8 | `subjects` | Campo del conocimiento OCDE para el que se utiliza el equipo. Clasificar en segundo nivel. | Array `[{ "scheme": "https://purl.org/pe-repo/ocde/ford", "value": "URI" }]` | MA | Repetible |
| 7.2.5.9 | `researchLine` | Línea de investigación institucional vinculada al equipo. | Texto libre | R | Repetible |
| 7.2.5.10 | `funded` | Financiamiento con el que se adquirió el equipo. | Objeto con `by` (OrgUnit) y `as` (código financiamiento) | MA | Repetible |
| 7.2.5.11 | `description` | Marca, serie, modelo y otros datos descriptivos. Formato recomendado: `"Marca: X. Serie: Y. Modelo: Z."` | Array `[{ "lang": "es", "value": "Marca: X. Serie: Y..." }]` | R | Repetible |
| 7.2.5.12 | `manufacturingCountry` | País de fabricación o ensamblaje. Permite calcular depreciación. | ISO 3166 — 2 caracteres: `"US"`, `"JP"`, `"DE"` | R | No repetible |
| 7.2.5.13 | `manufacturingDate` | Fecha de fabricación del equipo. | ISO 8601: `AAAA-MM-DD`, `AAAA-MM` o `AAAA` | R | No repetible |
| 7.2.5.14 | `acquisitionDate` | Fecha de adquisición del equipo por la institución. | ISO 8601: `AAAA-MM-DD`, `AAAA-MM` o `AAAA` | R | No repetible |
| 7.2.5.15 | `acquisitionAmount` | Monto de adquisición. Enteros sin decimales ni separadores. Incluir moneda ISO 4217. | Objeto `{ "value": 18000, "currency": "USD" }` | R | No repetible |
| — | `location` | Ubicación física dentro de la institución. | Objeto `{ "campus": "...", "lab": "..." }` | O | No repetible |
| — | `generatedOutputs` | Productos generados usando este equipamiento. | Array `["Products/..."]` | O | Repetible |
| — | `url` | URL del registro en el sistema de origen. | URL completa | O | No repetible |
| — | `lastModified` | Fecha de última modificación. | ISO 8601 | M | No repetible |

**Salida JSON completa:**

```json
{
  "id": "Equipments/82394874",
  "name": [{ "lang": "es", "value": "Cromatógrafo de gases con detector de flama o llama" }],
  "identifiers": [
    { "scheme": "https://w3id.org/cerif/vocab/IdentifierTypes#CRISID", "value": "F.5618" },
    { "scheme": "https://w3id.org/cerif/vocab/IdentifierTypes#SerialNumber", "value": "SN-CHROM-000123" }
  ],
  "type": "http://purl.org/coar/resource_type/c_2f33",
  "description": [
    { "lang": "es", "value": "Marca: VARIAN. Serie: GC0806B023. Modelo: 450GC. Horno para 3 columnas simultáneas, temperatura hasta 450ºC." }
  ],
  "owner": {
    "orgUnit": { "id": "OrgUnits/38498322", "name": "Universidad Nacional San Luis Gonzaga" }
  },
  "location": { "campus": "Sede Central", "lab": "Laboratorio de Química Analítica" },
  "subjects": [
    { "scheme": "https://purl.org/pe-repo/ocde/ford", "value": "https://purl.org/pe-repo/ocde/ford#1.04.00" }
  ],
  "funded": {
    "by": { "orgUnit": { "id": "OrgUnits/854622", "name": "FONDECYT" } },
    "as": { "id": "Fundings/508718", "value": "001-2018-FONDECYT-BM" }
  },
  "manufacturingCountry": "US",
  "manufacturingDate": "2015",
  "acquisitionDate": "2020-09-06",
  "acquisitionAmount": { "value": 18000, "currency": "USD" },
  "generatedOutputs": ["Products/258746"],
  "url": "https://cris.institucion.edu.pe/equipment/82394874",
  "lastModified": "2026-02-18T11:00:00Z"
}
```

---

### 5.6 Publication — Publicación

**Definición:** Publicación científica resultado de actividades de I+D+i: artículos, libros, actas, tesis, etc.
**CERIF:** `https://w3id.org/cerif/model#ResultPublication` | **Set:** `publications`

| ID | Campo JSON | Descripción | Formato / Valores | Persist. | Ocurrencia |
|----|-----------|-------------|-------------------|----------|------------|
| 7.2.6.1 | `id` | Código del sistema de origen. Sin espacios ni caracteres internacionales. | `"Publications/{id}"` | M | No repetible |
| 7.2.6.2 | `title` | Título original de la publicación. Subtítulo separado con `: `. Mayúsculas solo al inicio o en nombres propios. | Array `[{ "lang": "es", "value": "Título: subtítulo" }]` | M | Repetible |
| 7.2.6.3 | `type` | Tipo de recurso según vocabulario COAR. | Objeto `{ "scheme": "URI_COAR_vocab", "value": "URI_tipo" }` | M | No repetible |
| 7.2.6.4 | `access` | Nivel de acceso según COAR Access Rights. | URI COAR, ej. `http://purl.org/coar/access_right/c_abf2` (acceso abierto) | M | No repetible |
| 7.2.6.5 | `identifiers` (Handle) | Identificador Handle persistente del recurso. | Type: `"Handle"` | O | No repetible |
| 7.2.6.6 | `identifiers` (DOI) | Digital Object Identifier permanente. | Type: `"DOI"` — valor sin `https://doi.org/` | O | No repetible |
| 7.2.6.7 | `identifiers` (ISBN) | International Standard Book Number. | Type: `"ISBN"` — 13 dígitos con guiones | O | Repetible |
| 7.2.6.8 | `identifiers` (ISSN) | International Standard Serial Number. | Type: `"ISSN"` — 8 dígitos con guión | O | Repetible |
| 7.2.6.9 | `identifiers` (PMCID) | Identificador PubMed Central. | Type: `"PMCID"` | O | No repetible |
| 7.2.6.10 | `identifiers` (ISI-Number) | Identificador Web of Science. | Type: `"ISI-Number"` | O | No repetible |
| 7.2.6.11 | `identifiers` (SCP-Number) | Identificador Scopus. | Type: `"SCP-Number"` | O | No repetible |
| 7.2.6.12 | `identifiers` (URL) | URL de acceso al recurso. | Type: `"URL"` — URL completa | O | No repetible |
| 7.2.6.13 | `publishedIn` | Fuente donde fue publicado (revista, libro). Embebe referencia a otra Publication con al menos `id`, `type`, `title` e `issn` o `isbn`. | Objeto `{ "publication": { "id": "...", "type": "...", "title": [...], "issn": [...] } }` | O | No repetible |
| 7.2.6.14 | `partOf` | Recurso del que forma parte (ej. capítulo → libro). Embebe referencia a otra Publication. | Objeto `{ "publication": { "id": "...", "isbn": [...] } }` | O | No repetible |
| 7.2.6.15 | `authors` | Autores de la publicación. Cada autor tiene un objeto `person` embebido, sus `affiliations` al momento de la publicación y su `order`. | Array de objetos con `person`, `affiliations`, `order` | O | Repetible |
| 7.2.6.16 | `editors` | Editores de la publicación con `order`. | Array de objetos con `person` y `order` | O | Repetible |
| 7.2.6.17 | `publishers` | Editoriales que publican el recurso. | Array `[{ "orgUnit": { "id": "...", "name": [...] } }]` | O | Repetible |
| 7.2.6.18 | `publicationDate` | Fecha de publicación oficial. | ISO 8601: `AAAA-MM-DD`, `AAAA-MM` o `AAAA` | O | No repetible |
| 7.2.6.19 | `number` | Número de la publicación en la serie. | Alfanumérico, ej. `"5"` | O | No repetible |
| 7.2.6.20 | `volume` | Volumen de la publicación seriada. | Alfanumérico, ej. `"8"` | O | No repetible |
| 7.2.6.21 | `issue` | Fascículo de la publicación seriada. | Alfanumérico, ej. `"2"` | O | No repetible |
| 7.2.6.22 | `edition` | Edición (para libros). | Texto, ej. `"2a ed."` | O | No repetible |
| 7.2.6.23 | `startPage` | Página de inicio del artículo o capítulo. | Número como string, ej. `"285"` | O | No repetible |
| 7.2.6.24 | `endPage` | Página de fin del artículo o capítulo. | Número como string, ej. `"309"` | O | No repetible |
| 7.2.6.25 | `language` | Idioma(s) de la publicación. | Array ISO 639-1: `["es", "en"]` | O | Repetible |
| 7.2.6.26 | `license` | Condición de licencia. Puede incluir URI del tipo de licencia y URL de la licencia. | Array con objetos `{ "scheme": "...", "value": "..." }` y/o `{ "url": "..." }` | O | Repetible |
| 7.2.6.27 | `originatesFrom` | Proyectos y/o financiamientos de los que surge la publicación. | Array con `{ "project": { "id": "..." } }` y/o `{ "funding": { "id": "..." } }` | O | Repetible |
| 7.2.6.28 | `abstract` | Resumen. Repetible para distintos idiomas. | Array `[{ "lang": "es", "value": "..." }]` | O | Repetible |
| 7.2.6.29 | `version` | Versión según vocabulario COAR Version Types. | Objeto `{ "scheme": "http://purl.org/coar/version", "value": "URI_version" }` | R | No repetible |
| 7.2.6.30 | `keywords` | Palabras clave. Repetible para distintos idiomas. | Array `[{ "lang": "es", "value": "..." }]` | R | Repetible |
| 7.2.6.31 | `subjects` (OCDE) | Campo del conocimiento OCDE. | Array `[{ "scheme": "https://purl.org/pe-repo/ocde/ford", "value": "URI" }]` | R | Repetible |
| 7.2.6.32 | `subjects` (materia) | Materias adicionales (MeSH, etc.). | Array `[{ "scheme": "URI_vocab", "value": "término" }]` | R | Repetible |
| 7.2.6.33 | `sponsorship` | Texto de reconocimiento o patrocinio del trabajo. | Array `[{ "value": "Esta investigación fue financiada por..." }]` | R | Repetible |
| 7.2.6.34 | `advisors` | Asesores de la tesis/trabajo académico. | Objeto con array `advisors` de objetos Person | R | No repetible |
| 7.2.6.35 | `renatiType` | Tipo de trabajo según RENATI. Solo para tesis y trabajos académicos. | Objeto `{ "scheme": "https://purl.org/pe-repo/renati/type", "value": "URI_tipo" }` | R | No repetible |
| 7.2.6.36 | `inSupportOf` | Grado académico o título profesional otorgado con esta publicación. Solo para tesis. | Objeto con `qualification` | R | No repetible |
| 7.2.6.36.1.1 | ↳ `qualification.title` | Nombre del grado otorgado. | Texto, ej. `"Maestro en Banca y Finanzas"` | O | No repetible |
| 7.2.6.36.1.2 | ↳ `qualification.type` | Tipo de grado según RENATI level. | Objeto `{ "scheme": "https://purl.org/pe-repo/renati/level", "value": "URI" }` | O | No repetible |
| 7.2.6.36.1.3 | ↳ `qualification.discipline` | Programa académico según INEI CPESUTPT. | Objeto `{ "scheme": "https://purl.org/pe-repo/inei/cpesutpt", "value": "código" }` | O | Repetible |
| 7.2.6.36.1.4 | ↳ `qualification.grantor` | Institución otorgante del grado con jerarquía `partOf`. | Objeto `{ "orgUnit": { "id": "...", "partOf": { "orgUnit": {...} } } }` | O | No repetible |
| 7.2.6.36.1.5 | ↳ `qualification.jurors` | Jurados de la tesis. Embeben objetos Person con al menos `id`, `personName` e `identifiers`. | Array de objetos `{ "person": { ... } }` | R | Repetible |
| — | `lastModified` | Fecha de última modificación. | ISO 8601 | M | No repetible |

**Salida JSON completa:**

```json
{
  "id": "Publications/894518",
  "type": {
    "scheme": "https://www.openaire.eu/cerif-profile/vocab/COAR_Publication_Types",
    "value": "http://purl.org/coar/resource_type/c_0640"
  },
  "title": [
    { "lang": "es", "value": "Epidemiología de la sarna sarcóptica en vicuñas silvestres del Perú" },
    { "lang": "en", "value": "Epidemiology of sarcoptic mange in wild vicuñas of Peru" }
  ],
  "access": "http://purl.org/coar/access_right/c_abf2",
  "identifiers": [
    { "type": "Handle",     "value": "https://hdl.handle.net/20.500.12345/9876" },
    { "type": "DOI",        "value": "10.15381/pc.v24i1.16559" },
    { "type": "ISSN",       "value": "1609-9117" },
    { "type": "PMCID",      "value": "PMC7425445" },
    { "type": "ISI-Number", "value": "000245099300013" },
    { "type": "SCP-Number", "value": "2-s2.0-85045579729" },
    { "type": "URL",        "value": "https://revistas.unmsm.edu.pe/index.php/veterinaria/article/view/16559" }
  ],
  "publishedIn": {
    "publication": {
      "id": "Publications/REV001",
      "type": "http://purl.org/coar/resource_type/c_0640",
      "title": [{ "value": "Revista de Investigaciones Veterinarias del Perú" }],
      "issn": ["1609-9117"]
    }
  },
  "authors": [
    {
      "person": {
        "id": "Persons/28521427",
        "personName": {
          "familyNames": "Cabrera Sánchez",
          "firstNames": "Paolo Edgar",
          "fullName": "Paolo Edgar Cabrera Sánchez"
        },
        "identifiers": [
          { "scheme": "http://purl.org/pe-repo/concytec/terminos#dni", "value": "08755499" },
          { "scheme": "https://orcid.org", "value": "https://orcid.org/0000-0001-8247-8975" }
        ]
      },
      "affiliations": [
        { "orgUnit": { "id": "OrgUnits/38498322", "name": "Universidad Nacional San Luis Gonzaga" } }
      ],
      "order": 1
    }
  ],
  "publishers": [
    { "orgUnit": { "id": "OrgUnits/1", "name": [{ "lang": "es", "value": "Universidad Nacional Mayor de San Marcos" }] } }
  ],
  "publicationDate": "2023-08-25",
  "volume": "34",
  "issue": "2",
  "startPage": "285",
  "endPage": "309",
  "language": ["es", "en"],
  "license": [
    { "scheme": "https://www.openaire.eu/cerif-profile/vocab/LicenseTypes", "value": "CCAttribution(CCBY)" },
    { "url": "https://creativecommons.org/licenses/by/4.0/" }
  ],
  "originatesFrom": [
    { "project": { "id": "Projects/358478" } },
    { "funding": { "id": "Fundings/187597" } }
  ],
  "abstract": [
    { "lang": "es", "value": "Se estudió la epidemiología de la sarna en vicuñas silvestres del altiplano peruano..." }
  ],
  "version": { "scheme": "http://purl.org/coar/version", "value": "http://purl.org/coar/version/c_970fb48d4fbd8a85" },
  "keywords": [
    { "lang": "es", "value": "vicuñas" },
    { "lang": "es", "value": "sarna sarcóptica" }
  ],
  "subjects": [
    { "scheme": "https://purl.org/pe-repo/ocde/ford", "value": "https://purl.org/pe-repo/ocde/ford#4.03.04" },
    { "scheme": "http://id.nlm.nih.gov/mesh", "value": "Sarcoptes scabiei" }
  ],
  "renatiType": {
    "scheme": "https://purl.org/pe-repo/renati/type",
    "value": "https://purl.org/pe-repo/renati/type#tesis"
  },
  "inSupportOf": {
    "qualification": {
      "title": "Maestro en Ciencias Veterinarias",
      "type": {
        "scheme": "https://purl.org/pe-repo/renati/level",
        "value": "https://purl.org/pe-repo/renati/level#maestro"
      },
      "discipline": {
        "scheme": "https://purl.org/pe-repo/inei/cpesutpt",
        "value": "412077"
      },
      "grantor": {
        "orgUnit": {
          "id": "OrgUnits/F10",
          "name": [{ "value": "Facultad de Medicina Veterinaria" }],
          "partOf": {
            "orgUnit": {
              "id": "OrgUnits/1",
              "acronym": "UNMSM",
              "name": [{ "value": "Universidad Nacional Mayor de San Marcos" }],
              "identifiers": [{ "scheme": "ruc", "value": "20148092282" }]
            }
          }
        }
      },
      "jurors": [
        {
          "person": {
            "id": "Persons/288725",
            "personName": { "fullName": "Miriam Josefina Mariátegui Suarez" },
            "identifiers": [{ "scheme": "dni", "value": "10274587" }]
          }
        },
        {
          "person": {
            "id": "Persons/288726",
            "personName": { "fullName": "Rubén Jesús Lozada Martin" },
            "identifiers": [{ "scheme": "dni", "value": "08974125" }]
          }
        }
      ]
    }
  },
  "lastModified": "2026-02-20T12:00:00Z"
}
```

---

### 5.7 Patent — Patente

**Definición:** Propiedad intelectual en forma de patente de invención o modelo de utilidad generada en actividades de I+D+i.
**CERIF:** `https://w3id.org/cerif/model#ResultPatent` | **Set:** `patents`

| ID | Campo JSON | Descripción | Formato / Valores | Persist. | Ocurrencia |
|----|-----------|-------------|-------------------|----------|------------|
| 7.2.7.1 | `id` | Código del sistema de origen. | `"Patents/{id}"` | M | No repetible |
| 7.2.7.2 | `patentNumber` | Número oficial de la patente asignado por INDECOPI u otra entidad otorgante. | Alfanumérico, ej. `"000056-2010"` | M | No repetible |
| 7.2.7.3 | `title` | Título oficial de la patente. Repetible para distintos idiomas. | Array `[{ "lang": "es", "value": "..." }]` | M | Repetible |
| 7.2.7.15 | `type` | Tipo de recurso (patente de invención, modelo de utilidad) según COAR. | URI COAR, ej. `"http://purl.org/coar/resource_type/9DKX-KSAF"` | M | No repetible |
| 7.2.7.12 | `subjects` (CIP) | Clasificación Internacional de Patentes (IPC/OMPI). **Campo obligatorio.** No alterar URIs. | Array `[{ "scheme": "http://data.epo.org/linked-data/def/ipc/", "value": "URI_CIP" }]` | M | Repetible |
| 7.2.7.4 | `inventors` | Inventores de la patente. Embeben objetos Person con al menos `id`, `name` e `identifiers`. | Array `[{ "person": { "id": "...", "name": "...", "identifiers": [...] } }]` | O | Repetible |
| 7.2.7.5 | `holders` | Titulares de la patente (propietarios). Pueden ser personas u organizaciones. | Array `[{ "orgUnit": { "id": "...", "name": "...", "identifiers": [...] } }]` | O | Repetible |
| 7.2.7.6 | `issuer` | Entidad otorgante (INDECOPI para Perú). Embebe OrgUnit con `id`, `acronym` y `name`. | Objeto `{ "orgUnit": { "id": "...", "acronym": "INDECOPI", "name": "..." } }` | O | Repetible |
| 7.2.7.7 | `registrationDate` | Fecha de presentación de la solicitud original. | ISO 8601: `AAAA-MM-DD` | O | No repetible |
| 7.2.7.8 | `approvalDate` | Fecha de concesión oficial de la patente. | ISO 8601: `AAAA-MM-DD` | O | No repetible |
| 7.2.7.9 | `countryCode` | País donde fue otorgada la patente. | ISO 3166 — 2 caracteres: `"PE"` | O | No repetible |
| 7.2.7.10 | `language` | Idioma(s) de la patente. | Array ISO 639-1: `["es"]` | O | Repetible |
| 7.2.7.11 | `abstract` | Resumen técnico. Repetible para distintos idiomas. | Array `[{ "lang": "es", "value": "..." }]` | R | Repetible |
| 7.2.7.13 | `keywords` | Palabras clave. | Array `[{ "value": "..." }]` | O | Repetible |
| 7.2.7.14 | `subjects` (OCDE) | Campo del conocimiento OCDE. | Array `[{ "scheme": "https://purl.org/pe-repo/ocde/ford", "value": "URI" }]` | R | Repetible |
| 7.2.7.16 | `url` | URL del expediente en INDECOPI u otro sistema. | URL completa | O | No repetible |
| 7.2.7.17 | `originatesFrom` | Proyectos y/o financiamientos de los que surge la patente. | Array `[{ "project": { "id": "..." } }, { "funding": { "id": "..." } }]` | O | Repetible |
| 7.2.7.18 | `predecessor` | Patente predecesora (para continuaciones o divisiones). | Array `["Patents/..."]` | O | Repetible |
| — | `lastModified` | Fecha de última modificación. | ISO 8601 | M | No repetible |

**Salida JSON completa:**

```json
{
  "id": "Patents/284788",
  "type": "http://purl.org/coar/resource_type/9DKX-KSAF",
  "patentNumber": "000056-2010",
  "title": [
    { "lang": "es", "value": "Válvula check para un circuito cerrado de bombeo de aire para un envase dispensador" }
  ],
  "subjects": [
    { "scheme": "http://data.epo.org/linked-data/def/ipc/", "value": "http://data.epo.org/linked-data/def/ipc/A47K5-12" },
    { "scheme": "https://purl.org/pe-repo/ocde/ford", "value": "https://purl.org/pe-repo/ocde/ford#2.03.00" }
  ],
  "inventors": [
    {
      "person": {
        "id": "Persons/2016875",
        "name": "Lizeth María Zárate Aguilar",
        "identifiers": [{ "scheme": "dni", "value": "07928457" }]
      }
    },
    {
      "person": {
        "id": "Persons/2875998",
        "name": "Marco Antonio Chaco Gómez",
        "identifiers": [{ "scheme": "orcid", "value": "https://orcid.org/0000-0001-7291-6401" }]
      }
    }
  ],
  "holders": [
    {
      "orgUnit": {
        "id": "OrgUnits/385244",
        "name": "Industrias Arguelles y Servicios Generales",
        "identifiers": [{ "scheme": "ruc", "value": "20173136499" }]
      }
    }
  ],
  "issuer": {
    "orgUnit": {
      "id": "OrgUnits/355471",
      "acronym": "INDECOPI",
      "name": "Instituto Nacional de Defensa de la Competencia y de la Protección de la Propiedad Intelectual"
    }
  },
  "registrationDate": "2010-01-25",
  "approvalDate": "2012-01-16",
  "countryCode": "PE",
  "language": ["es"],
  "abstract": [{ "lang": "es", "value": "Sistema de válvula check para circuito cerrado de bombeo de aire en envases dispensadores." }],
  "keywords": [{ "value": "Dispensadores para jabón" }, { "value": "Válvula check" }],
  "url": "https://servicio.indecopi.gob.pe/portalSAE/Expedientes/consultaOIN.jsp?nroExpediente=000056-2010",
  "originatesFrom": [
    { "project": { "id": "Projects/358478" } },
    { "funding": { "id": "Fundings/187597" } }
  ],
  "lastModified": "2026-02-19T08:00:00Z"
}
```

---

### 5.8 Product — Producto de Investigación

**Definición:** Productos de investigación distintos a publicaciones y patentes: datasets, software, modelos, informes técnicos, etc.
**CERIF:** `https://w3id.org/cerif/model#ResultProduct`

| ID | Campo JSON | Descripción | Formato / Valores | Persist. | Ocurrencia |
|----|-----------|-------------|-------------------|----------|------------|
| 7.2.8.1 | `id` | Código del sistema de origen. | `"Products/{id}"` | M | No repetible |
| 7.2.8.2 | `type` | Tipo de recurso según COAR (dataset, software, modelo, informe técnico, etc.). | URI COAR resource type | M | No repetible |
| 7.2.8.20 | `access` | Nivel de acceso según COAR Access Rights. | URI COAR Access Rights | M | No repetible |
| 7.2.8.4 | `name` | Nombre del producto. Repetible para distintos idiomas. | Array `[{ "lang": "es", "value": "..." }]` | M | Repetible |
| 7.2.8.3 | `language` | Idioma(s) del producto. | Array ISO 639-1: `["es", "en"]` | O | Repetible |
| 7.2.8.5 | `versionInfo` | Versión del producto (especialmente para software y datasets). | Array de strings: `["1.0.0"]` | O | Repetible |
| 7.2.8.6 | `ark` | Identificador ARK (Archival Resource Key) del producto. | URL ARK | O | No repetible |
| 7.2.8.7 | `doi` | Digital Object Identifier. URL completa. | URL DOI | O | No repetible |
| 7.2.8.8 | `handle` | Identificador Handle persistente. | URL Handle | O | No repetible |
| 7.2.8.9 | `url` | URL de acceso al producto en el repositorio. | URL completa | O | No repetible |
| 7.2.8.10 | `creators` | Creadores del producto con orden. Embeben objetos Person. | Array `[{ "person": { "id": "...", "name": "..." }, "order": 1 }]` | O | Repetible |
| 7.2.8.11 | `publishers` | Editores o publicadores del producto. | Array `[{ "orgUnit": { "id": "...", "name": [...] } }]` | O | Repetible |
| 7.2.8.12 | `license` | Licencia del producto. | Array con objetos `{ "scheme": "...", "value": "..." }` y/o `{ "url": "..." }` | O | Repetible |
| 7.2.8.13 | `description` | Descripción del producto de investigación. Repetible para distintos idiomas. | Array `[{ "lang": "es", "value": "..." }]` | R | Repetible |
| 7.2.8.14 | `keywords` | Palabras clave. | Array `[{ "value": "..." }]` | O | Repetible |
| 7.2.8.15 | `subjects` (OCDE) | Campo del conocimiento OCDE. | Array `[{ "scheme": "https://purl.org/pe-repo/ocde/ford", "value": "URI" }]` | R | Repetible |
| 7.2.8.16 | `subjects` (materia) | Materias adicionales. | Array `[{ "scheme": "URI_vocab", "value": "término" }]` | R | Repetible |
| 7.2.8.17 | `partOf` | Recurso del que forma parte este producto. | Objeto `{ "id": "Products/..." }` | O | No repetible |
| 7.2.8.18 | `originatesFrom` | Proyectos y/o financiamientos de origen del producto. | Array `[{ "project": { "id": "..." } }]` | O | Repetible |
| 7.2.8.19 | `generatedBy` | Equipamientos usados para generar el producto. | Array `["Equipments/..."]` | O | Repetible |
| — | `lastModified` | Fecha de última modificación. | ISO 8601 | M | No repetible |

**Salida JSON completa:**

```json
{
  "id": "Products/DS-2023-001",
  "type": "http://purl.org/coar/resource_type/c_ddb1",
  "access": "http://purl.org/coar/access_right/c_abf2",
  "name": [
    { "lang": "es", "value": "Dataset de secuencias genómicas de vicuñas silvestres — Puno 2023" },
    { "lang": "en", "value": "Genomic sequence dataset of wild vicuñas — Puno 2023" }
  ],
  "language": ["es", "en"],
  "versionInfo": ["1.0.0"],
  "doi": "https://doi.org/10.1234/dataset.vicunas.2023",
  "url": "https://datos.unmsm.edu.pe/dataset/vicunas2023",
  "creators": [
    { "person": { "id": "Persons/28521427", "name": "Paolo Edgar Cabrera Sánchez" }, "order": 1 }
  ],
  "publishers": [
    { "orgUnit": { "id": "OrgUnits/1", "name": [{ "value": "Universidad Nacional Mayor de San Marcos" }] } }
  ],
  "license": [{ "url": "https://creativecommons.org/licenses/by/4.0/" }],
  "description": [
    { "lang": "es", "value": "Secuencias SNP de 120 especímenes de vicuña (Vicugna vicugna) del altiplano puneño." }
  ],
  "keywords": [{ "value": "vicuña" }, { "value": "genomics" }, { "value": "SNP" }],
  "subjects": [
    { "scheme": "https://purl.org/pe-repo/ocde/ford", "value": "https://purl.org/pe-repo/ocde/ford#1.06.05" }
  ],
  "originatesFrom": [{ "project": { "id": "Projects/358478" } }],
  "generatedBy": ["Equipments/82394874"],
  "lastModified": "2026-02-20T12:00:00Z"
}
```

---

## 6. Verbos OAI-PMH y respuestas JSON

### Identify

```json
{
  "OAI-PMH": {
    "@xmlns": "http://www.openarchives.org/OAI/2.0/",
    "@xmlns:xsi": "http://www.w3.org/2001/XMLSchema-instance",
    "@xsi:schemaLocation": "http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd",
    "responseDate": "2026-03-05T15:20:00Z",
    "request": { "@verb": "Identify", "#text": "https://cris.institucion.edu.pe/oai" },
    "Identify": {
      "repositoryName": "CRIS Institucional - Universidad Ejemplo",
      "baseURL": "https://cris.institucion.edu.pe/oai",
      "protocolVersion": "2.0",
      "adminEmail": "admin@institucion.edu.pe",
      "earliestDatestamp": "2018-01-01T00:00:00Z",
      "deletedRecord": "persistent",
      "granularity": "YYYY-MM-DDThh:mm:ssZ"
    }
  }
}
```

### ListMetadataFormats

```json
{
  "OAI-PMH": {
    "responseDate": "2026-03-05T15:20:05Z",
    "request": { "@verb": "ListMetadataFormats", "#text": "https://cris.institucion.edu.pe/oai" },
    "ListMetadataFormats": {
      "metadataFormat": [
        { "metadataPrefix": "oai_dc", "schema": "http://www.openarchives.org/OAI/2.0/oai_dc.xsd", "metadataNamespace": "http://www.openarchives.org/OAI/2.0/oai_dc/" },
        { "metadataPrefix": "perucris-cerif", "schema": "https://purl.org/pe-repo/perucris/cerif.xsd", "metadataNamespace": "https://purl.org/pe-repo/perucris/cerif" }
      ]
    }
  }
}
```

### ListSets

```json
{
  "OAI-PMH": {
    "responseDate": "2026-03-05T15:20:10Z",
    "request": { "@verb": "ListSets", "#text": "https://cris.institucion.edu.pe/oai" },
    "ListSets": {
      "set": [
        { "setSpec": "persons",      "setName": "Personas" },
        { "setSpec": "orgunits",     "setName": "Unidades organizativas" },
        { "setSpec": "publications", "setName": "Publicaciones" },
        { "setSpec": "projects",     "setName": "Proyectos" },
        { "setSpec": "fundings",     "setName": "Financiamientos" },
        { "setSpec": "equipments",   "setName": "Equipamientos" },
        { "setSpec": "patents",      "setName": "Patentes" }
      ]
    }
  }
}
```

### ListIdentifiers

```json
{
  "OAI-PMH": {
    "responseDate": "2026-03-05T15:21:20Z",
    "request": { "@verb": "ListIdentifiers", "@metadataPrefix": "perucris-cerif", "@set": "publications", "#text": "https://cris.institucion.edu.pe/oai" },
    "ListIdentifiers": {
      "header": [
        { "identifier": "oai:cris.institucion.edu.pe:Publications/894518", "datestamp": "2026-02-20T12:00:00Z", "setSpec": "publications" },
        { "identifier": "oai:cris.institucion.edu.pe:Publications/894519", "datestamp": "2026-02-21T10:30:00Z", "setSpec": "publications" }
      ],
      "resumptionToken": { "@cursor": "0", "@completeListSize": "250", "#text": "eyJwYWdlIjoyfQ==" }
    }
  }
}
```

### ListRecords — registros activo y eliminado (`persistent`)

```json
{
  "OAI-PMH": {
    "responseDate": "2026-03-05T15:21:00Z",
    "request": { "@verb": "ListRecords", "@metadataPrefix": "perucris-cerif", "@set": "publications", "#text": "https://cris.institucion.edu.pe/oai" },
    "ListRecords": {
      "record": [
        {
          "header": { "identifier": "oai:cris.institucion.edu.pe:Publications/894518", "datestamp": "2026-02-20T12:00:00Z", "setSpec": "publications" },
          "metadata": {
            "Publication": {
              "@id": "Publications/894518",
              "@xmlns": "https://purl.org/pe-repo/perucris/cerif",
              "Type": { "@xmlns": "https://www.openaire.eu/cerif-profile/vocab/COAR_Publication_Types", "#text": "http://purl.org/coar/resource_type/c_6501" },
              "Title": [{ "#text": "Efecto de la altitud en la variabilidad climática" }],
              "DOI": "10.1234/abcd.2026.0001",
              "PublicationDate": "2026-01-15",
              "Language": "es",
              "Abstract": { "#text": "Resumen de ejemplo." },
              "Subject": { "@scheme": "https://purl.org/pe-repo/ocde/ford", "#text": "https://purl.org/pe-repo/ocde/ford#1.05.01" }
            }
          }
        },
        {
          "header": {
            "identifier": "oai:cris.institucion.edu.pe:Publications/894500",
            "datestamp": "2026-03-01T08:00:00Z",
            "setSpec": "publications",
            "status": "deleted"
          }
        }
      ],
      "resumptionToken": { "@cursor": "0", "@completeListSize": "250", "#text": "eyJwYWdlIjoyfQ==" }
    }
  }
}
```

### GetRecord

```json
{
  "OAI-PMH": {
    "responseDate": "2026-03-05T15:21:30Z",
    "request": { "@verb": "GetRecord", "@metadataPrefix": "perucris-cerif", "@identifier": "oai:cris.institucion.edu.pe:Publications/894518", "#text": "https://cris.institucion.edu.pe/oai" },
    "GetRecord": {
      "record": {
        "header": { "identifier": "oai:cris.institucion.edu.pe:Publications/894518", "datestamp": "2026-02-20T12:00:00Z", "setSpec": "publications" },
        "metadata": {
          "Publication": {
            "@id": "Publications/894518",
            "@xmlns": "https://purl.org/pe-repo/perucris/cerif",
            "Title": [{ "#text": "Efecto de la altitud en la variabilidad climática" }],
            "DOI": "10.1234/abcd.2026.0001",
            "PublicationDate": "2026-01-15",
            "Language": "es"
          }
        }
      }
    }
  }
}
```

### Error OAI-PMH

```json
{
  "OAI-PMH": {
    "responseDate": "2026-03-05T15:21:40Z",
    "request": { "@verb": "ListRecords", "@metadataPrefix": "perucris-cerif", "@set": "unknown", "#text": "https://cris.institucion.edu.pe/oai" },
    "error": { "@code": "noSetHierarchy", "#text": "El set solicitado no existe o no está habilitado." }
  }
}
```

**Códigos de error:**

| Código | Descripción |
|--------|-------------|
| `badVerb` | Verbo no válido o ausente |
| `badArgument` | Argumento requerido faltante o inválido |
| `cannotDisseminateFormat` | `metadataPrefix` no soportado |
| `idDoesNotExist` | Identificador no encontrado |
| `noRecordsMatch` | Sin registros para los criterios dados |
| `noSetHierarchy` | Set no existe |
| `badResumptionToken` | Token inválido o expirado |

---

## 7. URIs y vocabularios controlados

### Identificadores por entidad

| Entidad | Campo | Scheme URI |
|---------|-------|-----------|
| Person | DNI | `http://purl.org/pe-repo/concytec/terminos#dni` |
| Person | ORCID | `https://orcid.org` |
| Person | ResearcherID | `https://w3id.org/cerif/vocab/IdentifierTypes#ResearcherID` |
| Person | Scopus Author ID | `https://w3id.org/cerif/vocab/IdentifierTypes#ScopusAuthorID` |
| OrgUnit | RUC | `https://purl.org/pe-repo/concytec/terminos#ruc` |
| OrgUnit | ROR | `https://ror.org` |
| OrgUnit | GRID | `https://www.grid.ac` |
| OrgUnit | ISNI | `https://isni.org` |
| OrgUnit | Tipo org. | `https://purl.org/pe-repo/concytec/tipoOrganizacion` |
| Funding | Award Number | `https://w3id.org/cerif/vocab/IdentifierTypes#AwardNumber` |
| Funding | FundRef ID | `https://w3id.org/cerif/vocab/IdentifierTypes#FundRefID` |
| Project | Código proyecto | `https://w3id.org/cerif/vocab/IdentifierTypes#ProjectReference` |
| Equipment | ID institucional | `https://w3id.org/cerif/vocab/IdentifierTypes#CRISID` |
| Equipment | Nro. serie | `https://w3id.org/cerif/vocab/IdentifierTypes#SerialNumber` |
| Patent | CIP (IPC) | `http://data.epo.org/linked-data/def/ipc/` |

### Vocabularios de clasificación comunes

| Vocabulario | URI base |
|-------------|----------|
| Campos OCDE (Ford) | `https://purl.org/pe-repo/ocde/ford` |
| Tipos actividad OCDE | `https://purl.org/pe-repo/ocde/tipoProyecto` |
| Tipos CTI CONCYTEC | `https://purl.org/pe-repo/concytec/terminos` |
| Tipo publicación COAR | `https://www.openaire.eu/cerif-profile/vocab/COAR_Publication_Types` |
| Acceso COAR | `http://purl.org/coar/access_right/` |
| Versión COAR | `http://purl.org/coar/version` |
| Licencias OpenAIRE | `https://www.openaire.eu/cerif-profile/vocab/LicenseTypes` |
| Tipos financiamiento | `https://www.openaire.eu/cerif-profile/vocab/OpenAIRE_Funding_Types` |
| Tipo trabajo RENATI | `https://purl.org/pe-repo/renati/type` |
| Nivel académico RENATI | `https://purl.org/pe-repo/renati/level` |
| Programas INEI | `https://purl.org/pe-repo/inei/cpesutpt` |
| MeSH NLM | `http://id.nlm.nih.gov/mesh` |

### Namespace CERIF PerúCRIS

| Parámetro | Valor |
|-----------|-------|
| `metadataPrefix` | `perucris-cerif` |
| `metadataNamespace` | `https://purl.org/pe-repo/perucris/cerif` |
| `schema XSD` | `https://purl.org/pe-repo/perucris/cerif.xsd` |
| OAI-PMH base | `http://www.openarchives.org/OAI/2.0/` |

### Formato de identificadores OAI

```
oai:{dominio_cris}:{NombreEntidad}/{InternalIdentifier}
```

Ejemplos:
```
oai:rais.unmsm.edu.pe:Publications/894518
oai:rais.unmsm.edu.pe:Persons/28521427
oai:rais.unmsm.edu.pe:Projects/358478
oai:rais.unmsm.edu.pe:OrgUnits/F10
oai:rais.unmsm.edu.pe:Fundings/187597
oai:rais.unmsm.edu.pe:Patents/284788
oai:rais.unmsm.edu.pe:Equipments/82394874
```

---

> **Fuentes oficiales:**
> • Directrices #PerúCRIS v1.1 — CONCYTEC, Junio 2024
> • Guía para creación de APIs JSON para Integración PerúCRIS — Oficina de TI CONCYTEC, Marzo 2026
> **Compatibilidad:** OpenAIRE Guidelines for CRIS Managers v1.1.1 · CERIF 1.5 · OAI-PMH 2.0
