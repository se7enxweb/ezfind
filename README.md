# eZ Find LS

eZ Find LS replaces the built-in database search of Exponential with
[Apache Solr](https://solr.apache.org/). It is for site owners and developers who
want search results ranked by relevance, the search words highlighted in the
results, facets ("narrow by content type, author, date ..."), spelling
suggestions, suggestions while typing, and the ability to pin chosen content to
the top of the results for a search phrase ("elevation").

It is a search engine plugin: once it is active, the normal `content/search`
page, the admin search box and `fetch( content, search )` all go through Solr,
and the extension adds a richer `fetch( ezfind, search )` for templates.

- Package: [`se7enxweb/ezfind`](https://packagist.org/packages/se7enxweb/ezfind)
- Source and issues: <https://github.com/se7enxweb/ezfind>
- License: GNU General Public License v2.0 (or any later version), see [LICENSE](LICENSE)

## Contents

- [What you get](#what-you-get)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Permissions](#permissions)
- [Cron jobs and command line scripts](#cron-jobs-and-command-line-scripts)
- [Upgrading](#upgrading)
- [Known limits](#known-limits)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License and credits](#license-and-credits)

## What you get

- **Relevance ranking.** A match in the name or a short attribute of an object
  counts more than a match deep in a text block. Boosts by class, attribute,
  datatype or meta field are configurable (`[IndexBoost]`, `[QueryBoost]`).
- **Highlighting** of the search words in the results (`[HighLighting]`).
- **Facets and filters** on content type, author, keywords, publication date and
  class attributes, through `fetch( ezfind, search )`.
- **Spell checking** ("did you mean ...", `[SpellCheck]`).
- **Suggestions while typing** in the admin search box, the site's search box and
  search page (ezwebin, ezdemo) and the ezflow search block. The list is an
  accessible combobox (keyboard and screen readers), in plain JavaScript.
- **Elevation**: an admin tab, *eZ Find*, where an editor pins an object to the
  top of the results for a search phrase, per language.
- **More like this**: content similar to a node, an object, a URL or a text.
- **Multilingual setups** with one Solr core per language (`[LanguageSearch]`),
  and one index shared by several installations (`[SiteSettings]`).
- **Indexing control**: commit-within, soft commits, scheduled optimizing,
  excluded classes (`[IndexOptions]`, `[IndexExclude]`).
- **An ezflow block** named *Search*, with spell checking and facets as options.
- A bundled **Solr 4.10.1** server with a ready core (`java/solr/ezp-default`) and
  service scripts for Debian, RHEL and Gentoo (`bin/scripts`).
- Translations of the interface in many languages (`translations/`).

## Requirements

- Exponential 6.x
- PHP 8.0 or later with the **curl** extension
- The ezjscore extension (part of Exponential), for the suggestions
- Java to run the bundled Solr (`java/start.jar`), or your own Solr server using
  the configuration in `java/solr/ezp-default/conf`
- One database table, `ezfind_elevate_configuration` (installation step 3)

## Installation

### With Composer

From the root of your Exponential installation:

```bash
composer require se7enxweb/ezfind
```

The Exponential legacy installer puts the extension in `extension/ezfind`.
Continue with step 2 below.

### By hand

1. Download a release from <https://github.com/se7enxweb/ezfind/releases> and
   unpack it so that the files are in `extension/ezfind/`
   (`extension/ezfind/ezinfo.php` must exist).

2. Activate the extension in `settings/override/site.ini.append.php`. eZ Find
   must come **before** ezwebin, ezdemo and ezflow, so that its search templates
   are used:

   ```ini
   [ExtensionSettings]
   ActiveExtensions[]=ezfind
   ActiveExtensions[]=ezjscore
   ActiveExtensions[]=ezwebin
   ```

   The extension's own `site.ini.append.php` switches the search engine to Solr
   (`[SearchSettings] SearchEngine=ezsolr`).

3. Create the table, with the schema file for any supported database:

   ```bash
   php bin/php/ezsqlinsertschema.php --type=mysql --user=<user> --password=<password> \
       extension/ezfind/share/db_schema.dba <database>
   ```

   or with the SQL file of your database (`sql/mysql`, `sql/postgresql`, `sql/oracle`):

   ```bash
   mysql -u <user> -p <database> < extension/ezfind/sql/mysql/schema.sql
   ```

4. Regenerate the autoload array and clear the caches:

   ```bash
   php bin/php/ezpgenerateautoloads.php --extension
   php bin/php/ezcache.php --clear-all
   ```

5. Start Solr. For a small site the bundled server is enough:

   ```bash
   cd extension/ezfind/java
   java -Dezfind -jar start.jar
   ```

   The user running Java needs write access to `extension/ezfind/java/solr/` and
   `extension/ezfind/java/logs/`. To run it as a service, follow the comments in
   `bin/scripts/<debian|rhel|gentoo>/solr`. Larger sites give Java more memory,
   for example `java -server -Xmx1500m -Xms1500m -Dezfind -jar start.jar`.

6. Index the existing content:

   ```bash
   php extension/ezfind/bin/php/updatesearchindexsolr.php -s <admin siteaccess> --clean
   ```

More detail, including Solr's index directory, is in [doc/INSTALL.md](doc/INSTALL.md);
a setup with one core per language is in [doc/multicore-HOWTO.txt](doc/multicore-HOWTO.txt).

## Configuration

Do not edit the files in `extension/ezfind/settings`; put your changes in
`settings/override/<file>.append.php` or in a siteaccess.

### solr.ini: where Solr is

```ini
[SolrBase]
# The Solr core eZ Find talks to
SearchServerURI=http://localhost:8983/solr/ezp-default
ConnectionTimeout=10
# Raise it for large indexes (long commits and optimizes)
ProcessTimeout=20
ProcessMaxRetries=2
# Basic authentication, if your Solr server asks for it
SearchServerAuthentication=disabled
SearchServerAuthenticationMethod=basic
SearchServerUserPass=
# Named shards (other cores or servers), used by 'distributed_search'
Shards[]
#Shards[eng-GB]=http://localhost:8983/solr/eng-GB
```

### ezfind.ini: what is indexed and how it is searched

| Section | Main settings | What they do |
| --- | --- | --- |
| `[SiteSettings]` | `URLProtocol`, `IndexPubliclyAvailable`, `SearchOtherInstallations` | URL prefix of results; share the index with other installations and search theirs. |
| `[SearchHandler]` | `DefaultSearchHandler` (`ezpublish`, `standard`, `simplestandard`, `heuristic`), `MaxBooleanClauses` | The Solr query handler; `ezpublish` (edismax) is recommended. |
| `[LanguageSearch]` | `SearchMainLanguageOnly`, `MultiCore`, `DefaultCore`, `LanguagesCoresMap[]` | Language fallback in results; one core per language. |
| `[AutoCompleteSettings]` | `AutoComplete`, `Limit`, `FacetField`, `FacetMethod`, `MinQueryLength` | Suggestions while typing. |
| `[SpellCheck]` | `SpellCheck`, `DefaultDictionary` | Spelling suggestions. |
| `[FacetSettings]` | `SiteNameList[]` | Display names of installations in facets. |
| `[SolrFieldMapSettings]` | `DatatypeMap[]`, `DatatypeMapSort[]`, `DatatypeMapFacet[]`, `DatatypeMapFilter[]`, `CustomMap[]`, `CustomAttributeMap[]` | How each datatype is stored in Solr; custom field classes. |
| `[IndexOptions]` | `OptimizeOnCommit`, `DisableDirectCommits`, `CommitWithin`, `DisableDeleteCommits`, `EnableSolrAttributeStorage`, `EnableSoftCommits` | When Solr commits and optimizes. |
| `[IndexBoost]` | `MetaField[]`, `Class[]`, `Attribute[]`, `Datatype[]`, `ReverseRelatedScale` | Index-time boosts. |
| `[IndexExclude]` | `ClassIdentifierList[]` | Classes that are never indexed. |
| `[SearchFilters]` | `FilterHiddenFromDB` | Filter hidden nodes using the database. |
| `[MoreLikeThis]` | `ExtractionFields`, `MinTermFreq`, `MinDocFreq`, `MinWordLength`, `MaxWordLength`, `MaxQueryTerms`, `BoostTerms` | Tuning of more-like-this. |
| `[HighLighting]` | `Enabled`, `RequireFieldMatch`, `SimplePre`, `SimplePost`, `SnippetsPerField`, `FragmentSize` | Highlighting in results. |
| `[IndexPlugins]` | `General[]`, `Class[]` | Your own PHP classes that change a document before it is indexed. |
| `[ExtendedAttributeFilters]` | `FiltersList[]` | Search filters such as `geodist` and `ezfindallwords`. |
| `[QueryBoost]` | `RawBoostQueries[]` | Query-time boosts. |

Every setting is explained in the comments of [settings/ezfind.ini](settings/ezfind.ini).
An override that turns on suggestions for a site and lets Solr batch the commits:

```ini
# settings/siteaccess/<site>/ezfind.ini.append.php
[AutoCompleteSettings]
AutoComplete=enabled
Limit=10
MinQueryLength=2

[IndexOptions]
DisableDirectCommits=true
CommitWithin=5000
```

### Settings the extension brings along

- `site.ini [SearchSettings]`: `SearchEngine=ezsolr`, `SearchViewHandling=template`,
  `AllowEmptySearch=enabled`, `EnableWildcard=true`.
- `cronjob.ini`: the parts listed under [Cron jobs](#cron-jobs-and-command-line-scripts).
- `block.ini`: the ezflow block `Search` (custom attributes `enable-spellcheck`
  and `show-facets`).
- `ezjscore.ini`: the server function `ezfind` (class `eZFindServerCallFunctions`),
  which answers the suggestions.
- `menu.ini`: the admin tab, below.

## Usage

### The admin tab

The extension adds an **eZ Find** tab to the administration's top menu. In the
admin4 design it shows a search icon (`Icon=search` in `[Topmenu_ezfind]` of
`settings/menu.ini.append.php`). The tab opens **Elevation** (`ezfind/elevate`):

1. Choose the object to promote: browse for it on the elevation page, or use
   the eZ Find entry of a node's context menu in the content tree.
2. Type the search phrase it should be elevated for and choose the language
   (or all languages).
3. Save. That object is now listed first when someone searches for the phrase.

The page also lists the existing elevations and filters them by phrase and
language. `ezfind/elevation_detail/<object id>` lists every phrase an object is
elevated for, and `ezfind/remove_elevation` removes one.

### Searching from a template

`fetch( ezfind, search, hash( ... ) )` returns a hash with `SearchResult` (the
nodes), `SearchCount`, `StopWordArray` and `SearchExtras` (facets, spelling
suggestions and more). A search with facets, as the bundled ezwebin search page
does it:

```smarty
{def $search = fetch( ezfind, search, hash(
        'query',       $search_text,
        'offset',      $view_parameters.offset,
        'limit',       10,
        'sort_by',     hash( 'score', 'desc' ),
        'facet',       fetch( ezfind, getDefaultSearchFacets ),
        'filter',      fetch( ezfind, filterParameters ),
        'spell_check', array( true() ) ) )}

{foreach $search.SearchResult as $node}
    {node_view_gui view=line content_node=$node}
{/foreach}
```

The parameters of `fetch( ezfind, search )` are `query`, `offset`, `limit`,
`facet`, `filter`, `sort_by`, `class_id`, `section_id`, `subtree_array`,
`ignore_visibility`, `limitation`, `as_objects`, `spell_check`,
`boost_functions`, `query_handler`, `enable_elevation`, `force_elevation`,
`publish_date`, `distributed_search`, `fields_to_return`,
`search_result_clustering`, `extended_attribute_filter` and
`class_attribute_id` (see `modules/ezfind/function_definition.php`).

A facet is a hash with a `field` (a meta field such as `class` or `author`, or
`<class identifier>/<attribute identifier>`), a `name` and a `limit`:

```smarty
'facet', array( hash( 'field', 'class',        'name', 'Content type', 'limit', 5 ),
                hash( 'field', 'article/tags', 'name', 'Keywords',     'limit', 5 ) )
```

A filter is a list of `field:value` strings. They are combined with `AND`, or
with `OR` when the first element is `'or'`:

```smarty
'filter', array( 'or', 'article/tags:Solr', 'article/tags:Search' )
```

`sort_by` takes `score`, `name`, `published`, `modified`, `class_name`,
`class_identifier`, `class_id`, `section_id`, `author`, `path` or a
`<class identifier>/<attribute identifier>`, each with `asc` or `desc`.

### Other fetch functions

```smarty
{* content similar to node 42 *}
{def $similar = fetch( ezfind, moreLikeThis, hash( 'query_type', 'nid', 'query', 42, 'limit', 5 ) )}

{* the facets and filters of the current request *}
{def $facets  = fetch( ezfind, facetParameters )
     $filters = fetch( ezfind, filterParameters )}

{* the elevations for a phrase, as the admin tab lists them *}
{def $elevated = fetch( ezfind, elevateConfiguration, hash( 'searchQuery', 'solr', 'limit', 10 ) )}

{* a raw request to a Solr core; the answer is in $raw.result *}
{def $raw = fetch( ezfind, rawSolrRequest, hash( 'baseURL', 'http://localhost:8983/solr/ezp-default',
                                                  'request', 'select',
                                                  'parameters', hash( 'q', '*:*', 'rows', 0 ) ) )}
```

`moreLikeThis` takes the `query_type` `nid` (node ID), `oid` (object ID), `url`
or `text`.

### Template operators

- `solr_escape` escapes the characters that have a meaning in the Solr query
  syntax: `{$search_text|solr_escape}`.
- `solr_quotes_escape` escapes double quotes, for a value inside a quoted phrase.

## Permissions

The module `ezfind` has one policy function, **`elevate`**. Give it to the
editors who manage elevation (Roles and policies, *ezfind / elevate*). It covers
the views `elevate`, `elevation_detail` and `remove_elevation`, and the admin tab
is only shown to users who have it.

Searching needs no extra policy: visitors search through `content/search`, and
the results only contain what the user may read.

## Cron jobs and command line scripts

Cron parts (run with `php runcronjobs.php <part>`):

| Part | Script | What it does |
| --- | --- | --- |
| `frequent` | `cronjobs/ezfindexsubtree.php` | Re-indexes the subtrees queued after a move or a section change. |
| `ezfindexsubtree` | `cronjobs/ezfindexsubtree.php` | The same, as a part of its own. |
| `ezfoptimizeindex` | `cronjobs/ezfoptimizeindex.php` | Optimizes the Solr index; run it daily or weekly. |

With delayed indexing (`site.ini [SearchSettings] DelayedIndexing=enabled`) the
kernel's `indexcontent` cron job sends the queued objects to Solr.

Scripts:

```bash
# (Re)build the whole index; --clean first removes this installation's documents,
# --conc runs several processes in parallel
php extension/ezfind/bin/php/updatesearchindexsolr.php -s <admin siteaccess> --clean --conc=4

# Commit or optimize by hand
php extension/ezfind/bin/php/ezsolrcommit.php -s <admin siteaccess>
php extension/ezfind/bin/php/ezsolroptimize.php -s <admin siteaccess>
```

`updatesearchindexsolr.php --help` lists its other options (`--clean-all`,
`--php-exec`, `--commit-within` and the database options).

## Upgrading

1. Replace `extension/ezfind` with the new release (`composer update se7enxweb/ezfind`,
   or unpack the archive over it).
2. Read the release notes on <https://github.com/se7enxweb/ezfind/releases> and
   the changelogs in [doc/changelogs](doc/changelogs); older upgrade steps are in
   [doc/upgrading](doc/upgrading).
3. If the Solr configuration in `java/solr/ezp-default/conf` changed, copy it to
   your Solr core, restart Solr and re-index.
4. Regenerate the autoloads and clear the caches (installation step 4).

## Known limits

- The bundled Solr is version 4.10.1. If you run your own Solr server, give it
  the schema and configuration from `java/solr/ezp-default/conf`.
- Every word of a search is one Solr clause per searched field. The query is
  shortened to stay within `MaxBooleanClauses`, and `SearchExtras.text_shortened`
  says when that happened.
- Suggestions add load to Solr; enable `AutoComplete` only where they are used.

## Troubleshooting

- **No results at all**: check that Solr answers at `SearchServerURI`
  (`curl http://localhost:8983/solr/ezp-default/admin/ping`) and that the index
  was built (`updatesearchindexsolr.php`).
- **New content is not found**: with `DisableDirectCommits=true` something else
  has to commit (`CommitWithin`, Solr's `autoCommit`, or `ezsolrcommit.php`); with
  delayed indexing the `indexcontent` cron job has to run.
- **The old search page is shown**: eZ Find must come before ezwebin, ezdemo and
  ezflow in `ActiveExtensions[]`; clear the template override cache afterwards
  (`php bin/php/ezcache.php --clear-id=template-override`).
- **"Call to undefined function curl_init()"**: install the PHP curl extension.
- **No suggestions in the search box**: set `[AutoCompleteSettings] AutoComplete=enabled`
  for the siteaccess and check that ezjscore is active.

## Contributing

Bug reports and pull requests are welcome at <https://github.com/se7enxweb/ezfind>.
Please state the Exponential, PHP and Solr versions and how to reproduce the
problem.

## License and credits

eZ Find is licensed under the GNU General Public License v2.0 (or any later
version), see [LICENSE](LICENSE). The bundled Apache Solr is licensed under the
Apache License, Version 2.0.

eZ Find was created by eZ Systems AS for eZ Publish, whose legacy line its
creator later discontinued, together with contributors from the eZ Community
(see [CREDITS.txt](CREDITS.txt)). 7x and the Exponential Foundation maintain it
for Exponential.
