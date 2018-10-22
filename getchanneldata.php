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
include('inc_certificados.php');

set_include_path($_SERVER["DOCUMENT_ROOT"].'/phpseclib');
//include('Crypt/RSA.php');
include('Crypt/AES.php');
include('Crypt/Random.php');




$userid=$con->real_escape_string($_REQUEST['userid']);
$certificadoA = $con->real_escape_string($_REQUEST['cert']);


//$rsa = new Crypt_RSA();

$certificadoA=base64_decode($certificadoA);




$sql="SELECT pubkey_signing,pubkey,account FROM usuarios WHERE id=$userid";
$res=$con->query($sql);
$row=$res->fetch_assoc();
$pubkey=$row["pubkey_signing"];
$user_publickey=$row["pubkey"];
$account=$row["account"];


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


$o_res=extraer_certificado($certificadoA,$pubkey,$privkey);

if(isset($o_res->e))
{
	echo json_encode($o_res);
	die;
}

$o_certA=$o_res;


$chid=$o_certA->channel;

//$chid=$clave;


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

$sql="SELECT * FROM other_keyshares WHERE id='$chid' AND tipo='canal' AND (cuenta=$account OR cuenta=-1)";
$res=$con->query($sql);
if($row=$res->fetch_assoc())
{
	$share=$row["share"];
	$cuenta_canal=$row["cuenta"];
}

if($cuenta_canal!=-1)
{
	$sql="SELECT * FROM other_keyshares_subscriptions WHERE keysh_id='$chid' AND usuario='$userid'";
	$res=$con->query($sql);
	if(!$row=$res->fetch_assoc())
	{
		echo "{";
		echo "\"e\":\"KSERROR user not allowed\",";
		echo "\"cert\":\"\"";
		echo "}";
		die;
	}
}

//$rsa = new Crypt_RSA();
/*$rsa->setHash("sha1");
$rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_OAEP);
$rsa->loadKey($user_publickey);
$encypted=base64_encode($rsa->encrypt($share));*/

openssl_public_encrypt($share,$encrypted,$user_publickey,OPENSSL_PKCS1_OAEP_PADDING);
$encrypted=base64_encode($encrypted);


$cert="$certA###$share";
/*$rsa->loadKey($privkey_signing); // private key
$rsa->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);
$signature = base64_encode($rsa->sign($cert));*/

openssl_sign($cert,$signature,$privkey_signing,OPENSSL_ALGO_SHA256);
$signature = base64_encode($signature);

echo "{";
echo "\"e\":\"OK\",";
echo "\"share\":\"$encrypted\",";
echo "\"cert\":\"$signature\"";
echo "}";

?>