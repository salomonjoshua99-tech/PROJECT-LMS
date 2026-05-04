<?php
class lib_sys{
public $db_h="localhost"; public $db_u="root"; public $db_p=""; public $db_n="library_db";
public $conn; public $fine_rate=5;
function connect(){
$this->conn=new mysqli($this->db_h,$this->db_u,$this->db_p,$this->db_n);
if($this->conn->connect_error){die("db error");}}
function addBook($t,$a,$y,$g){
$sql="INSERT INTO books(title,author,year,genre) VALUES('".$t."','".$a."',".$y.",'".$g."')";
$this->conn->query($sql); return $this->conn->insert_id;}
function getBook($id){
$sql="SELECT * FROM books WHERE book_id=".$id;
$result=$this->conn->query($sql); return $result->fetch_assoc();}
function borrowBook($sid,$bid,$days){
$due=date('Y-m-d',strtotime('+'.$days.' days'));
$sql="INSERT INTO borrow_records(student_id,book_id,borrow_date,due_date,status) VALUES(".$sid.",".$bid.",'".date('Y-m-d')."','".$due."','borrowed')";
$this->conn->query($sql); return true;}
function returnBook($rid){
$sql="SELECT * FROM borrow_records WHERE record_id=".$rid;
$r=$this->conn->query($sql)->fetch_assoc();
$due=strtotime($r['due_date']); $today=strtotime(date('Y-m-d'));
$diff=($today-$due)/(60*60*24); $fine=0;
if($diff>0){$fine=$diff*$this->fine_rate;}
$sql2="UPDATE borrow_records SET return_date='".date('Y-m-d')."', fine_amount=".$fine.", status='returned' WHERE record_id=".$rid;
$this->conn->query($sql2); return $fine;}
function listBooks(){
$sql="SELECT * FROM books";
$result=$this->conn->query($sql);
echo "<table border='1'><tr><th>ID</th><th>Title</th><th>Author</th><th>Year</th><th>Genre</th></tr>";
while($row=$result->fetch_assoc()){
echo "<tr><td>".$row['book_id']."</td><td>".$row['title']."</td><td>".$row['author']."</td><td>".$row['year']."</td><td>".$row['genre']."</td></tr>";}
echo "</table>";}
function searchBooks($kw){
$sql="SELECT * FROM books WHERE title LIKE '%".$kw."%' OR author LIKE '%".$kw."%'";
$result=$this->conn->query($sql); $books=array();
while($row=$result->fetch_assoc()){$books[]=$row;} return $books;}
function getOverdueBooks(){
$sql="SELECT br.*, b.title, s.name FROM borrow_records br JOIN books b ON br.book_id=b.book_id JOIN students s ON br.student_id=s.student_id WHERE br.due_date<'".date('Y-m-d')."' AND br.status='borrowed'";
$result=$this->conn->query($sql); $list=array();
while($row=$result->fetch_assoc()){$list[]=$row;} return $list;}
function generateReport(){
$totalBooks=$this->conn->query("SELECT COUNT(*) as c FROM books")->fetch_assoc()['c'];
$totalBorrowed=$this->conn->query("SELECT COUNT(*) as c FROM borrow_records WHERE status='borrowed'")->fetch_assoc()['c'];
$totalReturned=$this->conn->query("SELECT COUNT(*) as c FROM borrow_records WHERE status='returned'")->fetch_assoc()['c'];
$totalFines=$this->conn->query("SELECT SUM(fine_amount) as s FROM borrow_records WHERE fine_amount>0")->fetch_assoc()['s'];
echo "<h2>Library Report</h2>";
echo "<p>Total Books: ".$totalBooks."</p>";
echo "<p>Borrowed: ".$totalBorrowed."</p>";
echo "<p>Returned: ".$totalReturned."</p>";
echo "<p>Total Fines Collected: $".$totalFines."</p>";}}
$lib=new lib_sys();
$lib->connect();
if(isset($_GET['act'])){
if($_GET['act']=='add'){$lib->addBook($_POST['t'],$_POST['a'],$_POST['y'],$_POST['g']);}
elseif($_GET['act']=='list'){$lib->listBooks();}
elseif($_GET['act']=='report'){$lib->generateReport();}}
?>
