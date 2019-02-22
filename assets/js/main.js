// any CSS you require will output into a single css file (main.css in this case)
import '../sass/main.scss';
// Bootstrap
import 'bootstrap';
// MetisMenu
import 'metismenu';
// DataTable
import 'datatables';

$(function () {
  $(".metismenu").metisMenu();

  $('[data-toggle="dataTable"]').dataTable();
});