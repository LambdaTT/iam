SELECT 
  prf.ds_title, 
  prf.ds_key, 
  (CASE 
    WHEN user_filter.id_iam_accessprofile_user IS NULL THEN 'N' 
    ELSE 'Y' 
  END) as checked 
  FROM `IAM_ACCESSPROFILE` prf 
  LEFT JOIN ( 
    SELECT 
      upu.id_iam_accessprofile_user, 
      upu.id_iam_accessprofile 
      FROM `IAM_ACCESSPROFILE_USER` upu 
      RIGHT JOIN `IAM_USER` usr ON (usr.id_iam_user = upu.id_iam_user) 
      WHERE usr.ds_key = ?user_key? 
  ) user_filter ON (user_filter.id_iam_accessprofile = prf.id_iam_accessprofile) 