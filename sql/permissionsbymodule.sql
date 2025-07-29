SELECT 
  'E' as permission_type,
  ent.id_mdc_module_entity as entity_id, 
  ent.ds_entity_name as entity_name, 
  ent.ds_entity_label as entity_label, 
  prm.ds_key as permission_key, 
  prm.do_read, 
  prm.do_create, 
  prm.do_update, 
  prm.do_delete, 
  NULL as do_execute 
  FROM `IAM_ACCESSPROFILE_PERMISSION` prm 
  LEFT JOIN `MDC_MODULE_ENTITY` ent ON (ent.id_mdc_module_entity = prm.id_mdc_module_entity) 
  INNER JOIN `IAM_ACCESSPROFILE_MODULE` upm ON (upm.id_iam_accessprofile_module = prm.id_iam_accessprofile_module) 
  RIGHT JOIN `IAM_ACCESSPROFILE` prf ON (prf.id_iam_accessprofile = upm.id_iam_accessprofile) 
  WHERE upm.id_mdc_module = ?module_id? 
  AND prf.ds_key = ?profile_key? 
UNION 
SELECT 
  'C' as permmission_type, 
  NULL as entity_id, 
  NULL as entity_name, 
  cpm.ds_title as entity_label, 
  cpm.ds_key as permission_key, 
  NULL as do_read, 
  NULL as do_create, 
  NULL as do_update, 
  NULL as do_delete, 
  (CASE 
    WHEN profile_filter.id_iam_accessprofile_custom_permission IS NULL THEN 'N' 
    ELSE 'Y' 
  END) as do_execute 
  FROM `IAM_CUSTOM_PERMISSION` cpm 
  LEFT JOIN (SELECT 
              ucp.id_iam_accessprofile_custom_permission, 
              ucp.id_iam_custom_permission 
              FROM `IAM_ACCESSPROFILE_CUSTOM_PERMISSION` ucp 
              RIGHT JOIN `IAM_ACCESSPROFILE` prf ON (prf.id_iam_accessprofile = ucp.id_iam_accessprofile) 
              WHERE prf.ds_key = ?profile_key? 
              ) profile_filter ON(profile_filter.id_iam_custom_permission = cpm.id_iam_custom_permission)
  WHERE cpm.id_mdc_module = ?module_id? 