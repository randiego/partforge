Imports System.Xml
Imports System.Xml.XPath
Imports System.IO
Imports System.Text


Public Class BrowserWindowForm

    Public OutputValues As Dictionary(Of String, String) = New Dictionary(Of String, String)
    Public OutputReceived As Boolean = False
    Public PendingQueryUrl As String = ""
    Public BrowserTitle As String = ""

    Private Sub Panel1_Paint(ByVal sender As System.Object, ByVal e As System.Windows.Forms.PaintEventArgs) Handles Panel1.Paint

    End Sub

    Public Sub SetTitle(ByVal title As String)
        Me.BrowserTitle = title
        Me.UpdateTitles()
    End Sub

    Public Sub UpdateTitles()
        Me.Text = "PartForge Browser - " & Me.BrowserTitle
    End Sub

    '
    Public Sub SetQueryUrl(ByVal QueryUrl As String)
        Me.OutputValues.Clear()
        Me.OutputReceived = False
        Me.PendingQueryUrl = QueryUrl
    End Sub

    ' This event handler is fired when the webpage response is finished being read.  We want to egnore responses that are legit
    ' web pages since the user will be interacting with those.  Instead we want to look for something that looks like an XML response
    ' page.  For PartForge, this is somehting that starts with <output> and ends with </output>.
    Private Sub WebBrowser1_DocumentCompleted(ByVal sender As System.Object, ByVal e As System.Windows.Forms.WebBrowserDocumentCompletedEventArgs) Handles WebBrowser1.DocumentCompleted

        ' get the web page response and stick it in a memory stream (so that XPath can deal with it.
        Dim responsetext As String = WebBrowser1.DocumentText
        Dim ms As Stream = New MemoryStream()
        Dim sw As StreamWriter = New StreamWriter(ms) ' I think the default encoding here is UTF-8, which is what we want: http://msdn.microsoft.com/en-us/library/system.io.streamwriter.aspx
        sw.Write(responsetext)
        sw.Flush()

        ' Don't try to parse unless the response is in this format.  Problem is that the XPathDocument call hangs with a timeout for some reason.
        ' even though I am trying to catch an exception.  Might be encoding or something.
        If RegularExpressions.Regex.IsMatch(responsetext, "^<output>(.+)</output>") Then
            ' rewind the memory stream and try to parse it
            ms.Position = 0
            Try
                Dim xmlresp As XPathDocument = New XPathDocument(ms)
                If Not IsNothing(xmlresp) Then
                    Dim nav As XPath.XPathNavigator = xmlresp.CreateNavigator()
                    For Each field As XPathNavigator In nav.Select("output")
                        For Each child As XPathNavigator In field.SelectChildren(XPathNodeType.Element)
                            Me.OutputValues.Add(child.Name, child.Value)
                        Next
                    Next
                    Me.OutputReceived = True
                    Me.Hide()
                    Me.DialogResult = Windows.Forms.DialogResult.OK
                End If
            Catch ex As XmlException
                MsgBox("Error: " & ex.Message() & "  There is something wrong with the response from the web server.  It crashed the XML Parser.  Here it is: " & responsetext)
            End Try
        End If

    End Sub

    Private Sub BrowserWindowForm_Activated(ByVal sender As System.Object, ByVal e As System.EventArgs) Handles MyBase.Activated

    End Sub

    Private Sub BrowserWindowForm_VisibleChanged(ByVal sender As System.Object, ByVal e As System.EventArgs) Handles MyBase.VisibleChanged
        If Me.Visible Then

            '  WebBrowser1.Navigate(New Uri(""))
            ' WebBrowser1.Refresh()
            WebBrowser1.DocumentText = "<h1>Connecting...</h1>"
            My.Application.DoEvents()
            WebBrowser1.Navigate(New Uri(Me.PendingQueryUrl))

        'MessageBox.Show("We went visible")
        Else
            WebBrowser1.Stop()
        End If

    End Sub

    Private Sub Button2_Click(ByVal sender As System.Object, ByVal e As System.EventArgs)
    End Sub

    Private Sub Button1_Click(ByVal sender As System.Object, ByVal e As System.EventArgs) Handles Button1.Click
        Me.Hide()
        Me.DialogResult = Windows.Forms.DialogResult.Cancel
    End Sub

    Private Sub WebBrowser1_Navigated(ByVal sender As System.Object, ByVal e As System.Windows.Forms.WebBrowserNavigatedEventArgs) Handles WebBrowser1.Navigated
        Me.URLBox.Text = WebBrowser1.Url.ToString()
    End Sub

    Private Sub WebBrowser1_Navigating(ByVal sender As System.Object, ByVal e As System.Windows.Forms.WebBrowserNavigatingEventArgs) Handles WebBrowser1.Navigating
        Me.URLBox.Text = "Fetching: " & e.Url().ToString()
    End Sub
End Class