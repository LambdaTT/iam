SELECT 
  mdl.ds_key 
  FROM `MDC_MODULE` mdl 
  RIGHT JOIN `IAM_ACCESSPROFILE_MODULE` pmd ON (pmd.id_mdc_module = mdl.id_mdc_module) 
  RIGHT JOIN `IAM_ACCESSPROFILE_USER` pus ON (pus.id_iam_accessprofile = pmd.id_iam_accessprofile) 
  WHERE pus.id_iam_user = ?id_iam_user? 