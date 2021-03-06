// any CSS you require will output into a single css file (main.css in this case)
import '../sass/main.scss';
// Bootstrap
import 'bootstrap';
// MetisMenu
import 'metismenu';
// DataTable
import 'datatables.net-bs4';
// Select2
import 'select2';
// Bootstrap4-toggle
import 'bootstrap4-toggle';

import Routing from '../../vendor/friendsofsymfony/jsrouting-bundle/Resources/public/js/router.min.js';

const routes = require('./fos_js_routes.json');
Routing.setRoutingData(routes);

$(function () {

  // jQuery Accordion Menu Plugin For Bootstrap
  $(".metismenu").metisMenu();

  // Table plug-in for jQuery
  let dt = $('[data-toggle="dataTable"]').DataTable({
    dom:
      "<'row'<'col-sm-12 col-md-6'l><'col-md-6 col-sm-12 d-none'f>>" +
      "<'row'<'col-sm-12'tr>>" +
      "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 d-flex justify-content-end'p>>",
    lengthChange: false,
  });
  $('#dataTableSearchInput').keyup(function(){
    dt.search($(this).val()).draw() ;
  });

  // jQuery based replacement for select boxes
  $('body').find('select[multiple="multiple"]').select2({width: '100%'});

  // Highly flexible Bootstrap plugin that converts checkboxes into toggles
  $('input[data-toggle="toggle"]').on('change', function () {
    let toggle = $(this);
    let newValue = toggle.prop('checked');
    let oldValue = !newValue;

    let columnIndex = $(this).closest('td').index() + 1;
    let propertyName = $('table th.toggle:nth-child(' + columnIndex + ')').data('property-name');

    let modelName = toggle.attr('data-model-name');

    let toggleUrl = Routing.generate('admin_model', {
      action  : 'edit',
      model   : modelName,
      view    : 'list',
      id      : $(this).closest('tr').data('id'),
      property: propertyName,
      newValue: newValue.toString()
    });

    let toggleRequest = $.ajax({type: "GET", url: toggleUrl, data: {}});

    toggleRequest.done(function (result) {
    });

    toggleRequest.fail(function () {
      // in case of error, restore the original value and disable the toggle
      toggle.bootstrapToggle(oldValue === true ? 'on' : 'off');
      toggle.bootstrapToggle('disable');
    });
  });

  // Display delete modal
  $('.action-delete').on('click', function (e) {
    e.preventDefault();
    let id = $(this).parents('tr').first().data('id');

    $('#modal-delete').modal({backdrop: true, keyboard: true})
      .off('click', '#modal-delete-button')
      .on('click', '#modal-delete-button', function () {
        let deleteForm = $('#delete-form');
        deleteForm.attr('action', deleteForm.attr('action').replace('__id__', id));
        deleteForm.trigger('submit');
      });
  });

  // Sidebar toggle action
  $('.js-sidebar-toggler').click(function () {
    $('body').toggleClass('sidebar-mini');
  });


});