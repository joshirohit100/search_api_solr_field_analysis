# Search Api Aolr Field Analysis

This module is not intended to enable on production
but is more like the development module to review
or the solr field analysis if solr dashboard or UI
is not available.

This module simply shows what all analysis is done
and how a text is broken into tokens during
indexing and how match works during query.

###  To access the form
- Visit  ``"/admin/config/search/search-api"`` url.
- Click on the Solr server.
- Click the ``Field Analysis`` tab.

### To add this module in code base, add below in your composer.json `repositories` section
```
{
            "type": "package",
            "package": {
                "name": "drupal/search_api_solr_field_analysis",
                "version": "dev-main",
                "type":"drupal-module",
                "source": {
                    "url": "https://github.com/joshirohit100/search_api_solr_field_analysis.git",
                    "type": "git",
                    "reference": "main"
                }
            }
        }
```
