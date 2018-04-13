<?php 
function comprobar_permiso_heredado_dir($dir,$tipo_permiso,$userid,$becho=false)
{
	global $con;

	if($dir=="-1")
		return "0";
	if($dir=="-200")
		return "0";
	
	$sql="SELECT * FROM permisos WHERE id=$dir AND is_directory=1 AND user=$userid";
	//echo "$sql<br>";die;
	if($becho)
		echo "$sql";
	$res=$con->query($sql);
	$permiso_usuario="-1";
	$permiso_global="-1";
	if($row=$res->fetch_assoc())
	{
		$permiso_usuario=$row[$tipo_permiso];
	}
		
	$sql="SELECT * FROM permisos WHERE id=$dir AND is_directory=1 AND user=-1";
	if($becho)
		echo "$sql";
	$res=$con->query($sql);
	if($row=$res->fetch_assoc())
	{
		$permiso_global=$row[$tipo_permiso];
	}
	if($permiso_usuario=="1")
		return "1";
	else if($permiso_usuario=="0")
		return "0";
	if($permiso_global=="1")
		return "1";
	else if($permiso_global=="0")
		return "0";
						
	$sql="SELECT parent FROM directorios WHERE id=$dir";
	$res=$con->query($sql);
	$row=$res->fetch_row();
	$parent=$row[0];
	if($parent=="-1")
	{
		return "0";
	}	
	return comprobar_permiso_heredado_dir($parent,$tipo_permiso,$userid);
	
	return "0";
}

function comprobar_permiso_heredado($pid,$tipo,$tipo_permiso,$userid)
{
	global $con;

	if($tipo!="directory")
		$sql="SELECT directory as directorio FROM keyshares WHERE fileid=$pid";
	else
		$sql="SELECT parent as directorio FROM directorios WHERE id=$pid";
	//echo "$sql";
	$res=$con->query($sql);
	$row=$res->fetch_assoc();
	if(!$row)
		return "0";	
	$dir=$row["directorio"];
	return comprobar_permiso_heredado_dir($dir,$tipo_permiso,$userid);
}

function obtener_permiso($pid,$tipo,$tipo_permiso,$userid)
{
	global $con;

	if($tipo=="directory")
		$sql="SELECT * FROM permisos WHERE id=$pid AND is_directory=1 AND user=$userid";
	else
		$sql="SELECT * FROM permisos WHERE id=$pid AND is_directory=0 AND user=$userid";
	//echo "$sql";
	$res=$con->query($sql);
	$permiso_usuario="-1";
	if($row=$res->fetch_assoc())
	{
		$permiso_usuario=$row[$tipo_permiso];
	}

	if($tipo=="directory")
		$sql="SELECT * FROM permisos WHERE id=$pid AND is_directory=1 AND user=-1";
	else
		$sql="SELECT * FROM permisos WHERE id=$pid AND is_directory=0 AND user=-1";
	//echo "$sql";
	$res=$con->query($sql);
	$permiso_global="-1";
	if($row=$res->fetch_assoc())
	{
		$permiso_global=$row[$tipo_permiso];
	}

	$badmin=true;

	if($permiso_usuario=="0")
	{
		$badmin=false;
	}
	if($permiso_usuario=="-1")
	{
		if($permiso_global=="0")
		{
			$badmin=false;
		}
		if($permiso_global=="-1")
		{
			$permiso=comprobar_permiso_heredado($pid,$tipo,$tipo_permiso,$userid);
			if($permiso=="0")
			{
				$badmin=false;
			}
		}
	}
	return $badmin;
}


?>