Imports System.Xml
Imports System.Xml.XPath
Imports System.IO
Imports System.Data
Imports System.Text
Imports System.Net
Imports System.Net.WebRequest
Imports System.Web.Script.Serialization
Imports System.Web

Public Class PartForgeDocument

    Private logfilePath As String
    Private logfileStream As FileStream
    Private logfilestreamWriter As StreamWriter

    Private _effective_date As String = ""
    Private _item_serial_number As String = ""
    Private _force_new_object_or_version As String = ""  ' blank, "object", or "version"
    Private _disposition As String = ""
    Private _user As String = ""
    Private _typeversion_id As String = ""
    Private _typeobject_id As String = ""
    Private _xmldoc As XPathDocument = Nothing
    Public FileName As String = "Untitled"

    Private _data_items As Dictionary(Of String, String)
    Private _comment_items As Dictionary(Of String, String)
    Private _last_web_response_strings As Dictionary(Of String, String)
    Private _fileExtension As String

    Public SaveList As Dictionary(Of String, String)
    Public SavedItemVersion_id As String
    Public SavedComment_id As String
    Public SavedDocument_ids As List(Of String)

    Public Sub New(ByVal LogFileName As String, fileExtension As String)
        Me.logfilePath = LogFileName
        Me._fileExtension = fileExtension
        Me._data_items = New Dictionary(Of String, String)
        Me._comment_items = New Dictionary(Of String, String)
        Me._last_web_response_strings = New Dictionary(Of String, String)
        Me.SavedDocument_ids = New List(Of String)
        Me.SaveList = New Dictionary(Of String, String)
        Me.ClearData()
    End Sub

    Public Sub OpenLogFile()
        Dim strPath As String
        strPath = Me.logfilePath
        If System.IO.File.Exists(strPath) Then
            logfileStream = New FileStream(strPath, FileMode.Append, FileAccess.Write)
        Else
            logfileStream = New FileStream(strPath, FileMode.Create, FileAccess.Write)
        End If
        logfilestreamWriter = New StreamWriter(logfileStream)
    End Sub

    Public Sub CloseLogFile()
        logfilestreamWriter.Close()
        logfileStream.Close()
    End Sub

    Public Sub WriteLog(ByVal strComments As String)
        OpenLogFile()
        logfilestreamWriter.WriteLine(strComments)
        CloseLogFile()
    End Sub

    Public Sub ClearData()
        Me.FileName = ""
        Me._effective_date = Now().ToString("MM/dd/yyyy HH:mm")
        Me._disposition = ""
        Me._item_serial_number = ""
        Me._force_new_object_or_version = ""
        Me._user = ""
        Me._typeversion_id = ""
        Me._typeobject_id = ""
        Me._data_items.Clear()
        Me._comment_items.Clear()
        Me._last_web_response_strings.Clear()
        Me.SaveList.Clear()
        Me.SavedDocument_ids.Clear()
        Me.SavedItemVersion_id = ""
        Me.SavedComment_id = ""
    End Sub

    ' This method accepts two strings that represent two files to 
    ' compare. A return value of 0 indicates that the contents of the files
    ' are the same. A return value of any other value indicates that the 
    ' files are not the same.
    Private Function FileCompare(ByVal file1 As String, ByVal file2 As String) As Boolean
        Dim file1byte As Integer
        Dim file2byte As Integer
        Dim fs1 As FileStream
        Dim fs2 As FileStream

        ' Determine if the same file was referenced two times.
        If (file1 = file2) Then
            ' Return 0 to indicate that the files are the same.
            Return True
        End If

        ' Open the two files.
        fs1 = New FileStream(file1, FileMode.Open)
        fs2 = New FileStream(file2, FileMode.Open)

        ' Check the file sizes. If they are not the same, the files
        ' are not equal.
        If (fs1.Length <> fs2.Length) Then
            ' Close the file
            fs1.Close()
            fs2.Close()

            ' Return a non-zero value to indicate that the files are different.
            Return False
        End If

        ' Read and compare a byte from each file until either a
        ' non-matching set of bytes is found or until the end of
        ' file1 is reached.
        Do
            ' Read one byte from each file.
            file1byte = fs1.ReadByte()
            file2byte = fs2.ReadByte()
        Loop While ((file1byte = file2byte) And (file1byte <> -1))

        ' Close the files.
        fs1.Close()
        fs2.Close()

        ' Return the success of the comparison. "file1byte" is
        ' equal to "file2byte" at this point only if the files are 
        ' the same.
        Return ((file1byte - file2byte) = 0)
    End Function

    ' destPath is full path to archive folder, with trailing backslash
    ' this function returns a the full file name of the file that has identical contents as Me.FileName
    Public Function GetFirstExactMatchInArchive(ByVal destPath As String) As String
        Dim di As New IO.DirectoryInfo(destPath)
        Dim diar1 As IO.FileInfo() = di.GetFiles("*" & Me._fileExtension)
        Dim dra As IO.FileInfo
        GetFirstExactMatchInArchive = ""
        If (File.Exists(Me.FileName)) Then
            For Each dra In diar1
                If FileCompare(Me.FileName, dra.FullName) Then
                    GetFirstExactMatchInArchive = dra.FullName
                    Exit For
                End If
            Next
        End If
    End Function

    ' destPath is full path to archive folder, with trailing backslash
    Public Sub ArchiveXML(ByVal destPath As String, Optional ByVal destPrefix As String = "Uploaded_")
        If (File.Exists(Me.FileName)) Then
            Dim rawFile As String = Path.GetFileName(Me.FileName)
            'prepend date time string.
            Dim destFullFileName As String = destPath & destPrefix & Now().ToString("yyyyMMdd-HHmmss") & "_" & rawFile
            If Not File.Exists(destFullFileName) Then
                ' OK to move since there is nothing there now.
                File.Move(Me.FileName, destFullFileName)
            End If
        End If

    End Sub

    Public Sub LoadFromXmlFile(ByVal infilename As String)
        Me.ClearData()
        Me._xmldoc = New XPathDocument(infilename)
        If Not IsNothing(Me._xmldoc) Then
            Me.FileName = infilename
            Dim nav As XPath.XPathNavigator = Me._xmldoc.CreateNavigator()
            For Each field As XPathNavigator In nav.Select("measurement/header")
                For Each child As XPathNavigator In field.SelectChildren(XPathNodeType.Element)
                    Select Case child.Name
                        Case "effective_date"
                            If child.Value <> "" Then
                                Me._effective_date = child.Value
                            End If
                        Case "user"
                            Me._user = child.Value
                        Case "item_serial_number"
                            Me._item_serial_number = child.Value
                        Case "force_new_object_or_version"
                            Me._force_new_object_or_version = IIf(child.Value = "object", "object", IIf(child.Value = "version", "version", "")).ToString
                        Case "disposition"
                            Me._disposition = child.Value
                        Case "typeversion_id"
                            Me._typeversion_id = child.Value
                        Case "typeobject_id"
                            Me._typeobject_id = child.Value
                    End Select
                Next
            Next
            For Each field As XPathNavigator In nav.Select("measurement/data")
                For Each child As XPathNavigator In field.SelectChildren(XPathNodeType.Element)
                    If (child.Name = "field") And child.HasAttributes And child.GetAttribute("name", "") <> "" Then
                        Me._data_items(child.GetAttribute("name", "")) = child.Value
                    Else
                        Me._data_items(child.Name) = child.Value
                    End If
                Next
            Next
            For Each field As XPathNavigator In nav.Select("measurement/comment")
                For Each child As XPathNavigator In field.SelectChildren(XPathNodeType.Element)
                    If Me._comment_items.ContainsKey(child.Name) Then
                        ' It looks like we have more than one, so we will append with seperator.  There is probably a much better way to do this, but...
                        Me._comment_items(child.Name) = Me._comment_items(child.Name) & "|" & child.Value
                    Else
                        Me._comment_items(child.Name) = child.Value
                    End If

                Next
            Next
        End If


    End Sub

    Public ReadOnly Property EffectiveDate() As Date
        Get
            Dim out As Date
            If (_effective_date <> "") And DateTime.TryParse(_effective_date, out) Then
                EffectiveDate = out
            Else
                EffectiveDate = Nothing
            End If
        End Get
    End Property

    Public Property UserID() As String
        Get
            UserID = Me._user
        End Get
        Set(ByVal value As String)
            Me._user = value
        End Set
    End Property

    Public ReadOnly Property Data As Dictionary(Of String, String)
        Get
            Data = _data_items
        End Get
    End Property

    Public ReadOnly Property CommentItems As Dictionary(Of String, String)
        Get
            CommentItems = _comment_items
        End Get
    End Property

    Public ReadOnly Property LastResponse As Dictionary(Of String, String)
        Get
            LastResponse = _last_web_response_strings
        End Get
    End Property

    Public Function ItemObjectToPostVars() As String
        Dim varpairs As List(Of String) = New List(Of String)
        varpairs.Add("effective_date=" & HttpUtility.UrlEncode(Me._effective_date))
        If Me._disposition <> "" Then
            varpairs.Add("disposition=" & HttpUtility.UrlEncode(Me._disposition))
        End If
        If Me._item_serial_number <> "" Then
            varpairs.Add("item_serial_number=" & HttpUtility.UrlEncode(Me._item_serial_number))
        End If
        varpairs.Add("user_id=" & HttpUtility.UrlEncode(Me._user))
        If Me._typeversion_id <> "" Then
            varpairs.Add("typeversion_id=" & HttpUtility.UrlEncode(Me._typeversion_id))
        End If
        If Me._typeobject_id <> "" Then
            varpairs.Add("typeobject_id=" & HttpUtility.UrlEncode(Me._typeobject_id))
        End If
        Dim pair As KeyValuePair(Of String, String)
        For Each pair In _data_items
            ' 
            varpairs.Add(pair.Key & "=" & HttpUtility.UrlEncode(pair.Value))
        Next
        ItemObjectToPostVars = String.Join("&", varpairs.ToArray())
    End Function

    Public Function HasCommentText() As Boolean
        HasCommentText = False
        If Me._comment_items.ContainsKey("text") Then
            If Me._comment_items("text") <> "" Then
                HasCommentText = True
            End If
        End If
    End Function

    Public Function HasCommentFileAttachment() As Boolean
        HasCommentFileAttachment = False
        If Me._comment_items.ContainsKey("fileattachment") Then
            If Me._comment_items("fileattachment") <> "" Then
                HasCommentFileAttachment = True
            End If
        End If
    End Function

    Public Function CommentTextToPostVars(ByVal ItemVersion_Id As String) As String
        Dim varpairs As List(Of String) = New List(Of String)
        varpairs.Add("user_id=" & HttpUtility.UrlEncode(Me._user))
        varpairs.Add("itemversion_id=" & ItemVersion_Id)
        If Me._comment_items.ContainsKey("comment_date") Then
            varpairs.Add("comment_added=" & HttpUtility.UrlEncode(Me._comment_items("comment_date")))
        End If
        If Me._comment_items.ContainsKey("text") Then
            varpairs.Add("comment_text=" & HttpUtility.UrlEncode("[AUTOPOSTED]: " & Me._comment_items("text")))
        End If
        CommentTextToPostVars = String.Join("&", varpairs.ToArray())
    End Function

    Public Function DocumentToPostVars(ByVal Comment_Id As String) As String
        '  Look at http://codepulse.blogspot.com/2010/04/posting-files-to-via-httpwebrequest-in.html but also my VBA program which works well.
        ' http://randyvaio/sandbox/items/documents?comment_id=2805
        ' the name of the form needs to be "files"
        Dim varpairs As List(Of String) = New List(Of String)
        varpairs.Add("comment_id=" & Comment_Id)
        DocumentToPostVars = String.Join("&", varpairs.ToArray())
    End Function

    Private Function GetRequestResponse(ByRef request As HttpWebRequest, ByVal logResult As Boolean) As Boolean
        Dim Success As Boolean = True
        ServicePointManager.SecurityProtocol = SecurityProtocolType.Tls12
        Dim myWebResponse As WebResponse = request.GetResponse()
        Dim ReceiveStream As Stream = myWebResponse.GetResponseStream()
        Dim encode As Encoding = System.Text.Encoding.GetEncoding("utf-8")
        Dim readStream As New StreamReader(ReceiveStream, encode)
        Dim read(256) As [Char]
        Dim count As Integer = readStream.Read(read, 0, 256)
        Dim returnString As String = ""
        While count > 0
            ' Dump the 256 characters on a string and display the string onto the console. 
            Dim str As New [String](read, 0, count)
            returnString = returnString & str
            count = readStream.Read(read, 0, 256)
        End While

        If logResult Then
            WriteLog("Return String: " & returnString)
        End If

        ' Release the resources of stream object.
        readStream.Close()

        ' Release the resources of response object.
        myWebResponse.Close()

        Dim ser As JavaScriptSerializer = New JavaScriptSerializer()
        Dim dict As Dictionary(Of String, Object) = ser.Deserialize(Of Dictionary(Of String, Object))(returnString)

        For Each dictKey As String In dict.Keys
            If dictKey = "errormessages" Then
                If TypeOf dict("errormessages") Is ArrayList Then
                    Dim Errors As ArrayList = DirectCast(dict("errormessages"), ArrayList)
                    If Errors.Count > 0 Then
                        Dim strerrs As List(Of String) = New List(Of String)
                        For Each msg As Object In Errors
                            strerrs.Add(msg.ToString())
                        Next
                        Me._last_web_response_strings("errormessages") = String.Join(", ", strerrs.ToArray)
                        Success = False
                    End If
                ElseIf TypeOf dict("errormessages") Is Dictionary(Of String, Object) Then
                    Dim Errors As Dictionary(Of String, Object) = DirectCast(dict("errormessages"), Dictionary(Of String, Object))
                    If Errors.Count > 0 Then
                        Dim strerrs As List(Of String) = New List(Of String)
                        For Each msgkey As String In Errors.Keys
                            strerrs.Add(msgkey & ": " & Errors(msgkey).ToString())
                        Next
                        Me._last_web_response_strings("errormessages") = String.Join(", ", strerrs.ToArray)
                        Success = False
                    End If
                Else
                    If TypeOf dict(dictKey) Is String Then
                        Me._last_web_response_strings(dictKey) = dict(dictKey).ToString
                    End If
                End If

            Else
                Me._last_web_response_strings(dictKey) = dict(dictKey).ToString()
            End If
        Next

        If Me._last_web_response_strings.ContainsKey("errormessages") Then
            If (Me._last_web_response_strings("errormessages") <> "") Then
                Success = False
            End If
        End If

        GetRequestResponse = Success


    End Function

    Private Function HttpPostToPartForge(ByVal Url As String, ByVal PostVars As String) As Boolean
        Dim request As HttpWebRequest = DirectCast(WebRequest.Create(Url), HttpWebRequest)
        request.ContentType = "application/x-www-form-urlencoded"
        request.Method = "POST"
        Dim encoding As New ASCIIEncoding()
        Dim byte1 As Byte() = encoding.GetBytes(PostVars)
        request.ContentLength = byte1.Length
        Dim newStream As Stream = request.GetRequestStream()
        newStream.Write(byte1, 0, byte1.Length)
        newStream.Close()

        HttpPostToPartForge = Me.GetRequestResponse(request, True)

    End Function

    Private Function HttpGetTypeDefinitionFromPartForge(ByVal BaseUrl As String) As Boolean
        Dim Url As String = ""
        Dim PostVars As String = ""
        Dim Success As Boolean = False

        If Me._typeversion_id <> "" Then
            Url = BaseUrl & "types/versions/" & HttpUtility.UrlEncode(Me._typeversion_id) & "?max_depth=0"
        ElseIf Me._typeobject_id <> "" Then
            Url = BaseUrl & "types/objects/" & HttpUtility.UrlEncode(Me._typeobject_id) & "?max_depth=0"
        End If

        Dim request As HttpWebRequest = DirectCast(WebRequest.Create(Url), HttpWebRequest)
        request.ContentType = "application/x-www-form-urlencoded"
        request.Method = "GET"

        HttpGetTypeDefinitionFromPartForge = Me.GetRequestResponse(request, False)  ' dont log the results here since it's just too big
        ' results are left in Me._last_web_response_strings object, so for example, if you want to know what
        ' type of object this is, check if Me._last_web_response_strings.ContainsKey("type_category_name") Then
        ' check if Me._last_web_response_strings("type_category_name") is "Part" or "Procedure"
    End Function


    ' Sends a file and then gets the response that should be json with
    Public Function HttpSendFileToPartForge(ByVal Url As String, ByVal FileName As String) As Boolean
        Dim request As HttpWebRequest = DirectCast(WebRequest.Create(Url), HttpWebRequest)
        Dim boundary As String = "----MyAppBoundary" & DateTime.Now.Ticks.ToString("x")

        request.ContentType = "multipart/form-data; boundary=" & boundary
        request.Method = "POST"

        Dim inData As Byte() = My.Computer.FileSystem.ReadAllBytes(FileName)

        Dim newStream As Stream = request.GetRequestStream()
        Dim writer As BinaryWriter = New BinaryWriter(newStream)
        writer.Write(Encoding.ASCII.GetBytes("--" & boundary & vbNewLine))
        writer.Write(Encoding.ASCII.GetBytes("Content-Disposition: multipart/form-data; name=""files""; filename=""" & Mid$(FileName, InStrRev(FileName, "\") + 1) & """" & vbNewLine))
        writer.Write(Encoding.ASCII.GetBytes("Content-Type: application/octet-stream" & vbNewLine))
        writer.Write(Encoding.ASCII.GetBytes(vbNewLine))
        writer.Write(inData, 0, inData.Length)
        writer.Write(Encoding.ASCII.GetBytes(vbNewLine))
        writer.Write(Encoding.ASCII.GetBytes("--" & boundary & "--" & vbNewLine))
        writer.Close()

        newStream.Close()

        HttpSendFileToPartForge = Me.GetRequestResponse(request, True)

    End Function


    ' Save to PartForge DB.  Returns true if something was saved.  
    ' This looks at the SaveList dictionary and tries to save items that have not yet been successfully saved.
    ' It does this in the following order: itemversion, comment, documents
    Public Function SavePendingToDataBase(ByVal BaseUrl As String, ByRef GotSomethingUploaded As Boolean) As Boolean

        WriteLog(Now().ToString("MM/dd/yyyy HH:mm") & ": Starting to upload XML file: " & Me.FileName)

        Dim Success As Boolean = True
        If Not Me.SaveList.ContainsKey("itemversion_id") Then
            Me.SaveList("itemversion_id") = "new"
        End If

        If Not IsNumeric(Me.SaveList("itemversion_id")) Then
            Me.SaveList("itemversion_id") = "new"
            WriteLog("Saving new itemversion...")
            ' if we can't determine the object type, then we need to insist on new objects rather than new versions
            Dim ForceNewObject As Boolean = True
            ' now try to get the formal partforge definition from Blue by calling something like http://www.mydomain.com/partforge/types/objects/129?max_depth=0
            If Me.HttpGetTypeDefinitionFromPartForge(BaseUrl) Then
                Dim TypeCategory As String = ""     ' this will be empty, "Procedure" or "Part"
                If Me._last_web_response_strings.ContainsKey("type_category_name") Then
                    TypeCategory = Me._last_web_response_strings("type_category_name")
                    WriteLog("Looked up type of object: " & TypeCategory)
                    ForceNewObject = CBool(IIf(TypeCategory = "Procedure", True, False))   'Make new versions of parts, but create entirely new procedures each time
                    If (TypeCategory = "Procedure") And (Me._item_serial_number <> "") Then 'Also, here we make sure that item_serial_number is blank.
                        Me._item_serial_number = ""
                        WriteLog("<item_serial_number> tag removed since this is a procedure")
                    End If
                End If
            End If

            If Me._force_new_object_or_version = "object" Then ForceNewObject = True ' But permit overrides
            If Me._force_new_object_or_version = "version" Then ForceNewObject = False
            If ForceNewObject Then
                Success = Me.HttpPostToPartForge(BaseUrl & "items/objects?format=json", Me.ItemObjectToPostVars())
            Else
                Success = Me.HttpPostToPartForge(BaseUrl & "items/versions?format=json", Me.ItemObjectToPostVars())
            End If
            If Not Me.LastResponse.ContainsKey("itemversion_id") Or Not Me.LastResponse.ContainsKey("itemview_url") Then
                Success = False
            End If
            If Success Then
                GotSomethingUploaded = True
                Me.SaveList("itemversion_id") = Me.LastResponse("itemversion_id")
                Me.SaveList("itemview_url") = Me.LastResponse("itemview_url")
                WriteLog("Successful save of itemversion: itemversion_id=" & Me.LastResponse("itemversion_id") & ", itemview_url=" & Me.LastResponse("itemview_url"))
            Else
                Me.SaveList("itemversion_id") = "error"
                Me.SaveList("itemview_url") = ""
                WriteLog("Failed to save itemversion record: " & Me.LastResponse("errormessages"))
            End If
        End If

            ' success means that we have a valid itemversion_id that we can save a comment under if one exists.
            If Success And (Me.HasCommentText Or Me.HasCommentFileAttachment()) Then
                If Not Me.SaveList.ContainsKey("comment_id") Then
                    Me.SaveList("comment_id") = "new"
                End If

                If Not IsNumeric(Me.SaveList("comment_id")) Then
                    ' then we will save the comment now
                    WriteLog("Saving comment record...")
                    Me.SaveList("comment_id") = "new"
                    Success = Me.HttpPostToPartForge(BaseUrl & "items/comments?format=json", Me.CommentTextToPostVars(Me.SaveList("itemversion_id")))
                    If Success Then
                        GotSomethingUploaded = True
                        Me.SaveList("comment_id") = Me.LastResponse("comment_id")
                        WriteLog("Successful save of comment record: comment_id=" & Me.LastResponse("comment_id"))
                    Else
                        Me.SaveList("comment_id") = "error"
                        WriteLog("Failed to save comment record: " & Me.LastResponse("errormessages"))
                    End If
                End If
            End If

            If Success And Me.HasCommentFileAttachment() Then
                Dim i As Integer = 0
                Dim dockey As String
            For Each FName As String In New List(Of String)(Me._comment_items("fileattachment").Split(CChar("|")))
                dockey = "document_id[" & i.ToString() & "]"
                If Not Me.SaveList.ContainsKey(dockey) Then
                    Me.SaveList(dockey) = "new"
                End If
                If Not IsNumeric(Me.SaveList(dockey)) Then
                    WriteLog("Saving attachement (" & dockey & ")...")
                    ' we should try to save it, since it was not saved yet
                    Success = Me.HttpSendFileToPartForge(BaseUrl & "items/documents?format=json&comment_id=" & Me.SaveList("comment_id"), FName)
                    If Success Then
                        GotSomethingUploaded = True
                        Me.SaveList(dockey) = Me.LastResponse("document_id")
                        WriteLog("Successful save of file (" & FName & ") record: document_id=" & Me.LastResponse("document_id"))
                    Else
                        Me.SaveList(dockey) = "error"
                        WriteLog("Failed to save file record: " & Me.LastResponse("errormessages"))
                        Exit For
                    End If
                End If
                i = i + 1
            Next
        End If
            If (Not Success) And GotSomethingUploaded Then
                WriteLog("Even though something failed, it appears that at least something was uploaded.")
            End If
        WriteLog(IIf(Success, "Successful", "Unsuccessful").ToString & " end of Upload procedure for " & Me.FileName)
        SavePendingToDataBase = Success
    End Function


End Class
