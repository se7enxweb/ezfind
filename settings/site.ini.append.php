<?php /*

[SearchSettings]
# eZ Find overload search settings.
ExtensionDirectories[]=ezfind
SearchEngine=ezsolr
SearchViewHandling=template

# Empty search is allowed by default for eZ Find, since it does not
# use extra resources compared to regular search.
AllowEmptySearch=enabled

# Solr reads * and ? in a search text as wildcards (wor*, wo?d). It did so
# whatever this setting said; the query builder now honours it
# (eZFindQueryText::escapeSyntax()), and eZ Find keeps wildcards on, as they
# always were. false searches * and ? as text. A ? that ends a word ("what?")
# is never a wildcard: it ends a question.
EnableWildcard=true

[RegionalSettings]
TranslationExtensions[]=ezfind

[TemplateSettings]
ExtensionAutoloadPath[]=ezfind

*/ ?>
