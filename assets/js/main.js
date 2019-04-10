// any CSS you require will output into a single css file (main.css in this case)
import '../sass/main.scss';
// Bootstrap
import 'bootstrap';
// MetisMenu
import 'metismenu';
// DataTable
import 'datatables';
//Select2
import 'select2';

$(function () {
  $(".metismenu").metisMenu();

  $('[data-toggle="dataTable"]').dataTable();

  $('body').find('form select[multiple="multiple"]').select2();
});