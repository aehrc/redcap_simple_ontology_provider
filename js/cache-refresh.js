/**
 * Preview/apply UI for the ontology cache refresh pages (project and
 * control-center). Shared by both - they differ only in which categories
 * they list and whether stale rows can belong to more than one project.
 *
 * Self-initializes from a `#cache_refresh_app` element carrying
 * `data-scope` ("project" or "system") and `data-module-object` (the
 * JavaScript Module Object's location, from
 * initializeJavascriptModuleObject()/getJavascriptModuleObjectName()) -
 * this keeps the page's own PHP free of any inline <script> logic, which
 * this repo's no-inline-script CI check disallows outright.
 *
 * getJavascriptModuleObjectName() returns a dotted namespace path (e.g.
 * "ExternalModules.AEHRC.SimpleOntologyExternalModule"), not a single
 * global variable name - REDCap's own docs use it by echoing it directly
 * as a JS expression (`const module = <?=...?>;`). Since that's not
 * possible from a data attribute, resolveGlobalByPath() below walks the
 * path across nested objects instead of a flat `window[name]` lookup.
 */
function resolveGlobalByPath(path) {
  return String(path).split('.').reduce(function (value, key) {
    return value && value[key];
  }, window);
}

$(function () {
  var $app = $('#cache_refresh_app');
  if (!$app.length) {
    return;
  }
  simpleOntologyInitCacheRefresh(resolveGlobalByPath($app.data('module-object')), $app.data('scope'));
});

/**
 * @param {object} moduleObject The JavaScript Module Object from
 *   initializeJavascriptModuleObject()/getJavascriptModuleObjectName().
 * @param {"project"|"system"} scope
 */
function simpleOntologyInitCacheRefresh(moduleObject, scope) {
  var $category = $('#cache_refresh_category');
  var $previewBtn = $('#cache_refresh_preview');
  var $applyBtn = $('#cache_refresh_apply');
  var $results = $('#cache_refresh_results');
  var $status = $('#cache_refresh_status');

  var lastPreview = null;

  function renderResults(preview) {
    lastPreview = preview;
    $results.empty();
    $applyBtn.prop('disabled', true);

    if (scope === 'system' && preview.skippedProjects && preview.skippedProjects.length) {
      var $skipped = $('<div>').addClass('cache-refresh-skipped');
      $skipped.append($('<strong>').text(
        'Skipped projects (they define their own category with this name - use their project page instead):'
      ));
      var $list = $('<ul>');
      preview.skippedProjects.forEach(function (skipped) {
        $list.append($('<li>').text('Project ' + skipped.project_id + ': ' + skipped.reason));
      });
      $skipped.append($list);
      $results.append($skipped);
    }

    if (!preview.stale.length) {
      $results.append($('<p>').text('No stale cache entries found for this category.'));
      return;
    }

    var $table = $('<table>').addClass('cache-refresh-table');
    var $headerRow = $('<tr>');
    if (scope === 'system') {
      $headerRow.append($('<th>').text('Project'));
    }
    $headerRow.append($('<th>').append(
      $('<input>').attr({type: 'checkbox', id: 'cache_refresh_select_all', checked: true})
    ));
    ['Code', 'Currently cached label', 'New label'].forEach(function (label) {
      $headerRow.append($('<th>').text(label));
    });
    $table.append($('<thead>').append($headerRow));

    var $tbody = $('<tbody>');
    preview.stale.forEach(function (entry, index) {
      var $row = $('<tr>');
      if (scope === 'system') {
        $row.append($('<td>').text(entry.project_id));
      }
      $row.append($('<td>').append(
        $('<input>')
          .attr({type: 'checkbox', checked: true, 'data-index': index})
          .addClass('cache-refresh-entry')
      ));
      $row.append($('<td>').text(entry.value));
      $row.append($('<td>').text(entry.old_label));
      $row.append($('<td>').text(entry.new_label));
      $tbody.append($row);
    });
    $table.append($tbody);
    $results.append($table);
    $applyBtn.prop('disabled', false);

    $('#cache_refresh_select_all').on('change', function () {
      $('.cache-refresh-entry').prop('checked', $(this).is(':checked'));
    });
  }

  function preview() {
    var category = $category.val();
    if (!category) {
      $results.empty();
      $applyBtn.prop('disabled', true);
      return;
    }
    $status.text('Loading...');
    $results.empty();
    $applyBtn.prop('disabled', true);
    moduleObject.ajax('preview-cache-refresh', {category: category}).then(function (response) {
      $status.text('');
      if (response && response.error) {
        $results.text(response.error);
        return;
      }
      renderResults(response);
    }).catch(function () {
      $status.text('');
      $results.text('An error occurred while loading the preview.');
    });
  }

  $previewBtn.on('click', preview);
  $category.on('change', preview);

  $applyBtn.on('click', function () {
    if (!lastPreview) {
      return;
    }
    var entries = [];
    $('.cache-refresh-entry:checked').each(function () {
      var entry = lastPreview.stale[$(this).attr('data-index')];
      entries.push({project_id: entry.project_id, value: entry.value});
    });
    if (!entries.length) {
      return;
    }
    $status.text('Applying...');
    $applyBtn.prop('disabled', true);
    moduleObject.ajax('apply-cache-refresh', {category: $category.val(), entries: entries}).then(function (response) {
      $status.text('');
      if (response && response.error) {
        $results.text(response.error);
        return;
      }
      var count = response.updated.length;
      $status.text('Updated ' + count + ' entr' + (count === 1 ? 'y' : 'ies') + '.');
      preview();
    }).catch(function () {
      $status.text('');
      $results.text('An error occurred while applying the refresh.');
    });
  });

  if ($category.val()) {
    preview();
  }
}
