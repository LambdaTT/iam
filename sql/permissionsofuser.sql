SELECT 
  ent.ds_entity_name, 
  prm.do_read, 
  prm.do_create, 
  prm.do_update, 
  prm.do_delete 
  FROM `IAM_ACCESSPROFILE_PERMISSION` prm 
  JOIN `MDC_MODULE_ENTITY` ent ON (ent.id_mdc_module_entity = prm.id_mdc_module_entity) 
  JOIN `IAM_ACCESSPROFILE_MODULE` pmd ON (pmd.id_iam_accessprofile_module = prm.id_iam_accessprofile_module) 
  JOIN `IAM_ACCESSPROFILE_USER` pru ON (pru.id_iam_accessprofile = pmd.id_iam_accessprofile) 
  WHERE pru.id_iam_user = ?id_iam_user? 