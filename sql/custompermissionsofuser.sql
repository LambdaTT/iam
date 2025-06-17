SELECT 
  prm.ds_key 
  FROM `IAM_CUSTOM_PERMISSION` prm 
  RIGHT JOIN `IAM_ACCESSPROFILE_CUSTOM_PERMISSION` rel ON (rel.id_iam_custom_permission = prm.id_iam_custom_permission) 
  RIGHT JOIN `IAM_ACCESSPROFILE_USER` pru ON (pru.id_iam_accessprofile = rel.id_iam_accessprofile) 
  WHERE pru.id_iam_user = ?id_iam_user?