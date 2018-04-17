<?php 

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");



header("Content-Type: application/json; charset=UTF-8");
error_reporting(E_ERROR | E_WARNING | E_PARSE);


include_once("./db.php");
include_once("./inc-log.php");

$con = new mysqli($g_db_server, $g_db_user, $g_db_password,$g_db_name) or die("Error connecting to database");

include('inc_permisos.php');


set_include_path($_SERVER["DOCUMENT_ROOT"].'/phpseclib');
//include('Crypt/RSA.php');
include('Crypt/AES.php');
include('Crypt/Random.php');


$userid=$con->real_escape_string($_REQUEST['userid']);
$certificadoA = $con->real_escape_string($_REQUEST['cert']);



//$rsa = new Crypt_RSA();

$certificadoA=base64_decode($certificadoA);
$acertificadoA=explode('@#@#@$$',$certificadoA);
$certificado_encriptado=$acertificadoA[0];
$firma=$acertificadoA[1];



$sql="SELECT pubkey_signing,pubkey FROM usuarios WHERE id=$userid";
$res=$con->query($sql);
$row=$res->fetch_assoc();
if($row)
{
	$pubkey=$row["pubkey_signing"];
	$user_publickey=$row["pubkey"];	
}
else{
	$pubkey=$_REQUEST["user_pubkey_signing"];
	$user_publickey=$_REQUEST["user_pubkey"];	
}


$sql="SELECT privkey,privkey_signing,pubkey,pubkey_signing FROM config";
$res=$con->query($sql);
$row=$res->fetch_assoc();
$privkey=$row["privkey"];
$privkey_signing=$row["privkey_signing"];


/*$rsa->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);
$rsa->loadKey($pubkey);
$rsa->setHash("sha256");
if(!@$rsa->verify($certificado_encriptado, $firma))
{
	echo "{";
	echo "\"e\":\"KSERROR signature verification $certificadoA\",";
	echo "\"cert\":\"\"";
	echo "}";
	die;
}*/

if(!openssl_verify($certificado_encriptado,$firma,$pubkey,OPENSSL_ALGO_SHA256))
{
	echo "{";
	echo "\"message\":\"KSERROR signature verification\",";
	echo "\"cert\":\"\"";
	echo "}";
	die;
}

$cert_no_encriptado=false;

//if(!isset($_REQUEST["user_pubkey"]) || $_REQUEST["user_pubkey"]=="")
{
	$acertA=explode("#@@##",$certificado_encriptado);
	$encriptado=$acertA[0];
	$clave_encriptada=$acertA[1];
	$iv=$acertA[2];	
	/*$rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_OAEP);
	$rsa->setHash("sha1");
	$rsa->loadKey($privkey); // private key
	$clave=@$rsa->decrypt($clave_encriptada);*/

	openssl_private_decrypt($clave_encriptada,$clave,$privkey,OPENSSL_PKCS1_OAEP_PADDING);

	$cipher = new Crypt_AES(); // could use CRYPT_AES_MODE_CBC
	$cipher->setKeyLength(256);
	$cipher->setKey($clave);
	$cipher->setIV($iv);
	$certA=@$cipher->decrypt($encriptado);

	if($certA=="")
	{		
		$cert_no_encriptado=true;
		$certA=$certificado_encriptado;
	}
}
/*else{
	$certA=$certificado_encriptado;	
}*/


sqllog($userid,$certA);
$certAutf8=utf8_encode($certA);
$o_certA=json_decode($certAutf8);


$id=$o_certA->auxid;
$tipo=$o_certA->type;

if(!$id)
{
	echo "{";
	echo "\"message\":\"KSERROR Unknown error \",";
	echo "\"cert\":\"\"";
	echo "}";
	die;
}


function arreglar_clave($k)
{
	$res=str_replace('\n',"",$k);
	$res=str_replace("\n","",$res);
	$res=str_replace("-----BEGIN PUBLIC KEY-----","",$res);
	$res=str_replace("-----END PUBLIC KEY-----","",$res);
	
	return $res;
}


//$user_publickey=arreglar_clave($user_publickey);


//echo "$user_publickey<br>";

$sql="SELECT * FROM blobs WHERE owner_id=$id AND tipo='$tipo'";
$res=$con->query($sql);
if(!$res)
{
	//echo "cert: $certificado_encriptado firma: $firma certa: $certA";die;
	//echo $;
	echo "{";
	echo "\"message\":\"KSERROR Unexpected error\",";
	echo "\"cert\":\"\"";
	echo "}";
	die;	
}

if($row=$res->fetch_assoc())
{
	$share=$row["share"];
}


openssl_public_encrypt($share,$encrypted,$user_publickey,OPENSSL_PKCS1_OAEP_PADDING);
$encrypted=base64_encode($encrypted);

$cert="$certA###$share";

openssl_sign($cert,$signature,$privkey_signing,OPENSSL_ALGO_SHA256);
$signature = base64_encode($signature);

echo "{";
echo "\"e\":\"OK\",";
echo "\"share\":\"$encrypted\",";
echo "\"cert\":\"$signature\"";
echo "}";

?>