SELECT 
  dev.id_iam_device,
  dev.ds_key as deviceKey,
  dev.dt_created as deviceDtCreated,
  dev.tx_useragent_info,
  usr.*
  FROM `IAM_DEVICE` dev
  JOIN `IAM_DEVICE_SESSION` ds ON ds.id_iam_device = dev.id_iam_device
  JOIN `IAM_SESSION` us ON us.id_iam_session = ds.id_iam_session
  JOIN `IAM_USER` usr ON usr.id_iam_user = us.id_iam_user
  