SELECT 
  mdl.ds_title, 
  mdl.ds_key,  
  mdl.id_apm_module,  
  (CASE 
    WHEN profile_filter.id_iam_accessprofile_module IS NULL THEN 'N' 
    ELSE 'Y' 
  END) as checked
  FROM `APM_MODULE` mdl 
  LEFT JOIN ( 
    SELECT 
      pmd.id_iam_accessprofile_module, 
      pmd.id_apm_module 
      FROM `IAM_ACCESSPROFILE_MODULE` pmd 
      RIGHT JOIN `IAM_ACCESSPROFILE` prf ON (prf.id_iam_accessprofile = pmd.id_iam_accessprofile) 
      WHERE prf.ds_key = ?profileKey? 
  ) profile_filter ON (profile_filter.id_apm_module = mdl.id_apm_module) 