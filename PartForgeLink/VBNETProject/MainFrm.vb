Imports System.IO
Imports System.Xml

Public Class MainFrm
    Public iniFileName As String = "PartForgeLink.ini"
    Public inDir As String  ' should be something like C:\PartForgeLink\PendingUploads\
    Public doneDir As String ' should be something like C:\PartForgeLink\CompletedUploads\
    Public logFileName As String ' should be something like C:\PartForgeLink\CompletedUploads\LogFile.txt
    Public PartForgeBaseUrl As String ' should be something like  http://www.mydomain.com/partforge/
    Public fileExtension As String ' should be something like .pforgex


    Private Sub Button3_Click(ByVal sender As System.Object, ByVal e As System.EventArgs) Handles Button3.Click
        InitializeView()
    End Sub

    Private Sub RefreshControls()
        UploadCheckedBtn.Enabled = (ListView1.CheckedItems.Count > 0)
    End Sub

    Public Sub EnsureWorkingfolders()
        Directory.CreateDirectory(Me.inDir)
        Directory.CreateDirectory(Me.doneDir)
    End Sub

    Private Function ProcessIniFile() As Boolean
        Dim oIniFile As New IniFile
        ProcessIniFile = True
        Dim fullIniFile As String
        Dim PartForgeLoginUrl As String
        Dim sPos As Int32
        fullIniFile = Directory.GetCurrentDirectory() & "\" & Me.iniFileName
        If File.Exists(fullIniFile) And oIniFile.LoadFile(fullIniFile) Then
            PartForgeLoginUrl = oIniFile.Items("PartForgeLoginUrl")
            If PartForgeLoginUrl = "" Then
                MsgBox("the key PartForgeLoginUrl in " & fullIniFile & " should be something like http://www.mydomain.com/partforge/user/login but is blank.")
                ProcessIniFile = False
            Else
                sPos = InStr(PartForgeLoginUrl, "/user/login")
                If sPos > 0 Then
                    Me.PartForgeBaseUrl = PartForgeLoginUrl.Substring(0, sPos)
                Else
                    MsgBox("the key PartForgeLoginUrl in " & fullIniFile & " should be something like http://www.mydomain.com/partforge/user/login but is set to something else.")
                End If
            End If
            Me.inDir = oIniFile.Items("InDirectory")
            If Me.inDir = "" Then
                MsgBox("the key InDirectory in " & fullIniFile & " should be something like .\PendingUploads\ but is blank.")
                ProcessIniFile = False
            End If
            Me.doneDir = oIniFile.Items("DoneDirectory")
            If Me.doneDir = "" Then
                MsgBox("the key DoneDirectory in " & fullIniFile & " should be something like .\CompletedUploads\ but is blank.")
                ProcessIniFile = False
            End If
            Me.logFileName = oIniFile.Items("LogFileName")
            If Me.logFileName = "" Then
                MsgBox("the key LogFileName in " & fullIniFile & " should be something like .\CompletedUploads\ but is blank.")
                ProcessIniFile = False
            End If
            Me.fileExtension = oIniFile.Items("FileExtension")
            If Me.fileExtension = "" Then
                MsgBox("the key FileExtension in " & fullIniFile & " should be something like .pforgex but is blank.")
                ProcessIniFile = False
            End If

        Else
            ProcessIniFile = False
            MsgBox("The file " & Me.iniFileName & " could not be found or openned in the location " & Directory.GetCurrentDirectory())
        End If

    End Function

    Public Sub InitializeView()
        Dim di As New IO.DirectoryInfo(inDir)
        Dim diar1 As IO.FileInfo() = di.GetFiles("*" & Me.fileExtension)
        Dim dra As IO.FileInfo
        Dim dupFileName As String

        Dim inDoc As PartForgeDocument
        Me.Text = "PartForge Link (" & Me.PartForgeBaseUrl & ")"
        ListView1.Items.Clear()
        For Each dra In diar1
            inDoc = New PartForgeDocument(Me.logFileName, Me.fileExtension, Me.inDir)
            Try
                inDoc.LoadFromXmlFile(dra.FullName)
                ' check to see if an identical file has already been uploaded.
                dupFileName = inDoc.GetFirstExactMatchInArchive(Me.doneDir)
                Dim skippingFile As Boolean = False
                If (dupFileName <> "") Then
                    If MsgBox("The file " & dra.FullName & " is identical to one that has already been uploaded (" & dupFileName & ").  Do you want to upload it again?", MsgBoxStyle.YesNo) = MsgBoxResult.No Then
                        inDoc.ArchiveXML(Me.doneDir, "Skipped_")
                        skippingFile = True
                    End If
                End If
                If Not skippingFile Then
                    'For Each StdPumpDescription As PumpTypesDocumentClass.Pump In in_pumpCurveStandards.pumpsDoc.Pumps
                    Dim item As ListViewItem = New ListViewItem()
                    item.Text = Path.GetFileName(inDoc.FileName)
                    item.SubItems.Add(Path.GetDirectoryName(inDoc.FileName))
                    item.SubItems.Add(inDoc.EffectiveDate.ToString())
                    item.SubItems.Add("Pending")
                    ListView1.Items.Add(item)
                End If
            Catch ex As XmlException
                MsgBox(ex.Message() & "  There is something wrong with the format of the input file (" & dra.FullName & ").")
            End Try

        Next

        RefreshControls()
    End Sub

    ' returns a string which is the URL of the last uploaded item
    Private Function UploadCheckedItems() As String
        Dim GotSomethingUploaded As Boolean
        UploadCheckedItems = ""
        Dim doc As PartForgeDocument = New PartForgeDocument(Me.logFileName, Me.fileExtension, Me.inDir)
        If (ListView1.CheckedItems.Count > 0) Then
            For Each idx As Integer In ListView1.CheckedIndices
                ' Dim idx As Integer = ListView1.CheckedIndices(0)
                Dim FName As String = ListView1.Items(idx).SubItems(1).Text & "\" & ListView1.Items(idx).Text
                doc.LoadFromXmlFile(FName)
                GotSomethingUploaded = False
                Dim doneTryingToGetUserID As Boolean = True
                If doc.UserID = "" Then
                    doneTryingToGetUserID = False
                    Do Until doneTryingToGetUserID

                        BrowserWindowForm.SetQueryUrl(Me.PartForgeBaseUrl & "struct/whoami")
                        BrowserWindowForm.SetTitle("Please enter your login information...")
                        Dim browserResult As System.Windows.Forms.DialogResult = BrowserWindowForm.ShowDialog()
                        If browserResult = System.Windows.Forms.DialogResult.OK Then
                            doc.UserID = BrowserWindowForm.OutputValues("login_id")
                            doneTryingToGetUserID = True
                        ElseIf browserResult = Windows.Forms.DialogResult.Cancel Then
                            doc.UserID = ""
                            doneTryingToGetUserID = True
                        End If
                    Loop
                End If
                If doneTryingToGetUserID And doc.UserID <> "" Then
                    Dim doneTryToSave As Boolean = False
                    Dim FullSuccess As Boolean = False
                    Do Until doneTryToSave
                        doneTryToSave = True
                        Try
                            Me.Cursor = Cursors.AppStarting
                            My.Application.DoEvents()

                            FullSuccess = doc.SavePendingToDataBase(Me.PartForgeBaseUrl, GotSomethingUploaded)
                            If FullSuccess Then
                                UploadCheckedItems = doc.LastResponse("itemview_url")
                                ListView1.Items(idx).SubItems(3).Text = "Success"
                                doc.ArchiveXML(Me.doneDir)
                            Else
                                Dim MsgPre As String = IIf(GotSomethingUploaded, "Could not complete the upload, however at least some portion was uploaded.  If you retry, you might end up with duplicate entries.", "Nothing uploaded.").ToString
                                If MsgBox("Could not upload " & FName & ".  Message: " & doc.LastResponse("errormessages") & "  " & MsgPre, MsgBoxStyle.RetryCancel) = MsgBoxResult.Retry Then
                                    doneTryToSave = False
                                Else
                                    ListView1.Items(idx).SubItems(3).Text = "Fail: " & doc.LastResponse("errormessages")
                                    If MsgBox("Do you want to keep this upload file in the queue to try later?", MsgBoxStyle.YesNo) = MsgBoxResult.No Then
                                        doc.ArchiveXML(Me.doneDir)
                                    End If
                                End If
                            End If
                        Catch ex As Exception
                            Dim MsgPre As String = IIf(GotSomethingUploaded, "Could not complete the upload, however at least some portion was uploaded.  If you retry, you might end up with duplicate entries.", "Nothing uploaded.").ToString
                            If MsgBox("Could not upload " & FName & ".  Exception: " & ex.Message() & MsgPre, MsgBoxStyle.RetryCancel) = MsgBoxResult.Retry Then
                                doneTryToSave = False
                            Else
                                ListView1.Items(idx).SubItems(3).Text = "Fail: " & ex.Message()
                                If MsgBox("Do you want to keep this upload file in the queue to try later?", MsgBoxStyle.YesNo) = MsgBoxResult.No Then
                                    doc.ArchiveXML(Me.doneDir)
                                End If
                            End If
                        Finally
                            Me.Cursor = Cursors.Default
                        End Try
                    Loop
                End If
            Next
        Else
            MsgBox("No items selected.")

        End If
        InitializeView()
    End Function

    ' Try to upload the selected XML document
    Private Sub Button2_Click(ByVal sender As System.Object, ByVal e As System.EventArgs) Handles UploadCheckedBtn.Click
        Dim ViewUrl As String = UploadCheckedItems()
        If ViewUrl <> "" Then ' we seem to have done something useful and are ready to jump someplace if we want
            If MsgBox("Done.  Data has been uploaded to PartForge.  Do you want to open the last item in a browser?", MsgBoxStyle.YesNo) = MsgBoxResult.Yes Then
                Try
                    Me.Cursor = Cursors.AppStarting
                    Process.Start(ViewUrl)
                Catch ex As Exception
                Finally
                    Me.Cursor = Cursors.Default
                End Try
            End If
        End If
    End Sub

    Private Sub Button4_Click(ByVal sender As System.Object, ByVal e As System.EventArgs)
    End Sub

    Private Sub Button5_Click(ByVal sender As System.Object, ByVal e As System.EventArgs)

    End Sub

    Private Sub ListView1_Click(ByVal sender As System.Object, ByVal e As System.EventArgs) Handles ListView1.Click

    End Sub

    Private Sub Panel1_Paint(ByVal sender As System.Object, ByVal e As System.Windows.Forms.PaintEventArgs) Handles Panel1.Paint

    End Sub

    Private Sub ListView1_ItemChecked(ByVal sender As System.Object, ByVal e As System.Windows.Forms.ItemCheckedEventArgs) Handles ListView1.ItemChecked
        RefreshControls()
    End Sub

    Private Sub Button1_Click(ByVal sender As System.Object, ByVal e As System.EventArgs) Handles Button1.Click
        Me.Close()
    End Sub

    Private Sub ListView1_SelectedIndexChanged(sender As System.Object, e As System.EventArgs) Handles ListView1.SelectedIndexChanged

    End Sub

    Private Sub MainFrm_Shown(ByVal sender As System.Object, ByVal e As System.EventArgs) Handles MyBase.Shown
        If Not ProcessIniFile() Then
            Me.Close()
        End If
        EnsureWorkingfolders()
        InitializeView()
        For Each lvi As ListViewItem In ListView1.Items
            lvi.Checked = True
        Next
        RefreshControls()
        If ListView1.Items.Count > 0 Then
            Dim SingPlur As String = IIf(ListView1.Items.Count = 1, "is 1 item", "are " & ListView1.Items.Count & " items").ToString
            If MsgBox("There " & SingPlur & " ready for uploading to PartForge.  Upload now?", MsgBoxStyle.YesNo) = MsgBoxResult.Yes Then
                Dim ViewUrl As String = UploadCheckedItems()
                If ViewUrl <> "" Then ' we seem to have done something useful and are ready to jump someplace if we want
                    If MsgBox("Done.  Data has been uploaded to PartForge.  Do you want to open the last item in a browser?", MsgBoxStyle.YesNo) = MsgBoxResult.Yes Then
                        Try
                            Me.Cursor = Cursors.AppStarting
                            Process.Start(ViewUrl)
                        Catch ex As Exception
                        Finally
                            Me.Cursor = Cursors.Default
                        End Try
                    End If
                    Me.Close()
                End If
            End If
        Else
            MsgBox("Nothing to upload from the location " & Me.inDir)
            Me.Close()
        End If

    End Sub
End Class
