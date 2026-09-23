using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Drawing;
using System.Linq;
using System.Text;
using System.Threading.Tasks;
using System.Windows.Forms;

namespace project_bitsa
{
    public partial class Form1 : Form
    {
        public int size;
        string paymint;
        int price = 0;
        int typing;
        int oddings = 0;
        int sizeprice = 0;
        int totalprice = 0;
        int odd = 0;

        public Form1()
        {
            InitializeComponent();
        }

        private void Form1_Load(object sender, EventArgs e)
        {

        }

        private void groupBox1_Enter(object sender, EventArgs e)
        {

        }

        private void radioButton1_CheckedChanged(object sender, EventArgs e)
        {
            sizeprice = 1000;
            label3.Text = Convert.ToString(odd + sizeprice + oddings);
        }

        private void radioButton2_CheckedChanged(object sender, EventArgs e)
        {
            sizeprice = 500;
            label3.Text = Convert.ToString(odd + sizeprice + oddings);
        }

        private void radioButton3_CheckedChanged(object sender, EventArgs e)
        {
            sizeprice = 250;
            label3.Text = Convert.ToString(odd + sizeprice + oddings);
        }

        private void radioButton6_CheckedChanged(object sender, EventArgs e)
        {
            oddings = 100;
            label3.Text = Convert.ToString(odd + sizeprice + oddings);
        }

        private void radioButton7_CheckedChanged(object sender, EventArgs e)
        {
            oddings = 60;
            label3.Text = Convert.ToString(odd + sizeprice + oddings);
        }

        private void radioButton5_CheckedChanged(object sender, EventArgs e)
        {
            oddings = 40;
            label3.Text = Convert.ToString(odd + sizeprice + oddings);
        }

        private void groupBox3_Enter(object sender, EventArgs e)
        {
            //totalprice = totalprice + 10;
        }

        private void label3_Click(object sender, EventArgs e)
        {
            label3.Text = Convert.ToString(totalprice + size + sizeprice);
        }

        private void checkBox1_CheckedChanged(object sender, EventArgs e)
        {
            if (checkBox1.Checked)
            {
                odd += 10;
            }
            else
            {
                odd -= 10;
            }
            label3.Text = Convert.ToString(odd + sizeprice + oddings);
        }

        private void checkBox2_CheckedChanged(object sender, EventArgs e)
        {
            if (checkBox2.Checked)
            {
                odd += 10;
            }
            else
            {
                odd -= 10;
            }
            label3.Text = Convert.ToString(odd + sizeprice + oddings);
        }

        private void checkBox3_CheckedChanged(object sender, EventArgs e)
        {
            if (checkBox3.Checked)
            {
                odd += 10;
            }
            else
            {
                odd -= 10;
            }
            label3.Text = Convert.ToString(odd + sizeprice + oddings);
        }

        private void checkBox4_CheckedChanged(object sender, EventArgs e)
        {
            if (checkBox4.Checked)
            {
                odd += 10;
            }
            else
            {
                odd -= 10;
            }
            label3.Text = Convert.ToString(odd + sizeprice + oddings);
        }

        // ==========================================
        // الإضافات الناقصة تم إدراجها أدناه:
        // ==========================================

        private void button1_Click(object sender, EventArgs e)
        {
            // 1. حساب السعر الإجمالي الكلي
            totalprice = sizeprice + oddings + odd;

            // 2. قراءة المبلغ المدفوع من مربع النص وتوليد المتبقي
            int paied = 0;
            int.TryParse(textBox1.Text, out paied);

            int remaining = paied - totalprice;
            // 3. تجهيز نص الفاتورة ليطابق ما في الصورة
            string invoiceMessage = "price of size: \t\t" + sizeprice + "\n" +
                                   "price of main tipping: \t" + oddings + "\n" +
                                   "price of addings: \t" + odd + "\n\n" +
                                   "total pice: \t\t" + totalprice + "\n" +
                                   "paied: \t\t\t" + paied + "\n" +
                                   "remaining: \t\t" + remaining;

            // 4. عرض النافذة المنبثقة
            MessageBox.Show(invoiceMessage, "pizza invoice", MessageBoxButtons.OK);
        }
    }
}













































enable
configure terminal
hostname S1
no ip domain-lookup
enable secret class
banner motd #
Unauthorized access is strictly prohibited.
#
line con 0
password cisco
login
logging synchronous
exit
line vty 0 15
password cisco
login
logging synchronous
exit

vlan 10
name Student
vlan 20
name Faculty
vlan 99
name Management
exit

interface fastEthernet0/6
switchport mode access
switchport access vlan 10
no shutdown
exit

interface fastEthernet0/1
switchport mode trunk
exit

interface range fastEthernet0/2-5, fastEthernet0/7-10
shutdown
exit

interface range fastEthernet0/11, fastEthernet0/21
switchport mode access
switchport access vlan 20
shutdown
exit

interface range fastEthernet0/12-20, fastEthernet0/22-23
switchport mode access
switchport access vlan 10
shutdown
exit

interface fastEthernet0/24
switchport mode access
shutdown
exit

interface range gigabitEthernet0/1-2
shutdown
exit

interface vlan1
no ip address
exit

interface vlan99
ip address 192.168.1.11 255.255.255.0
no shutdown
exit

ip http server
ip http secure-server
end
copy running-config startup-config
