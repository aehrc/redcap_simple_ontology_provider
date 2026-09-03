function SIMPLE_ontology_changed(service, category){
  var newSelection = ('SIMPLE' == service) ? category : '';
  $('#simple_ontology_category').val(newSelection);
}
