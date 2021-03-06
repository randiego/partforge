﻿Imports System.IO
Imports System.Text

Public Class IniFile
    Dim m_aItems() As String
    Dim m_nItemsCount As Int32

    Dim m_sPathFileName As String = ""

    Public Const INIFILE_ERR_FILENAME_REQUIRED = -1000
    Public Const INIFILE_ERR_KEY_REQUIRED = -1001

    Public Function LoadFile(ByVal FileName As String) As Boolean
        Dim fs As FileStream
        Dim sr As StreamReader

        Dim sLine As String

        FileName = Trim(FileName)

        Try
            m_sPathFileName = FileName

            '-- Initialize array items
            m_nItemsCount = 0

            If System.IO.File.Exists(m_sPathFileName) = False Then      '-- Not found
                Return False
            End If

            fs = New FileStream(m_sPathFileName, FileMode.OpenOrCreate, FileAccess.Read, FileShare.Read)
            sr = New StreamReader(fs, Encoding.UTF8)

            While True
                sLine = sr.ReadLine()

                m_nItemsCount = m_nItemsCount + 1

                ReDim Preserve m_aItems(m_nItemsCount)

                m_aItems(m_nItemsCount - 1) = sLine

                If sr.EndOfStream Then
                    Exit While
                End If
            End While

            sr.Close()
            fs.Close()

            sr = Nothing
            fs = Nothing

            Return True

        Catch ex As Exception
            sr = Nothing
            fs = Nothing

            Return False
        End Try
    End Function

    Public Function SaveFile(FileName As String) As Boolean
        Dim fs As FileStream
        Dim sw As StreamWriter

        Dim sWrite As String
        Dim i As Int32

        FileName = Trim(FileName)

        Try
            sWrite = ""

            For i = 1 To m_nItemsCount
                sWrite = sWrite & m_aItems(i - 1) & vbCrLf
            Next


            fs = New FileStream(m_sPathFileName, FileMode.Create, FileAccess.Write, FileShare.None)
            sw = New StreamWriter(fs, Encoding.UTF8)

            sw.Write(sWrite)

            sw.Close()
            fs.Close()

            sw = Nothing
            fs = Nothing

            Return True

        Catch ex As Exception
            sw = Nothing
            fs = Nothing

            Return False
        End Try
    End Function

    Default Public Property Items(ByVal Key As String) As String
        Get
            Dim nIndex As Int32
            Dim sRetVal As String = ""
            Dim nPos As Int32

            Key = Trim(Key)

            If Key = "" Then
                Err.Raise(INIFILE_ERR_KEY_REQUIRED, , "Missing key parameter")
            End If

            nIndex = _GetIndex(Key)

            If nIndex = -1 Then     '-- Not found
                Return ""
            End If


            '-- Found

            sRetVal = m_aItems(nIndex)

            nPos = InStr(sRetVal, "=")

            If (nPos > 0) Then
                sRetVal = sRetVal.Substring(nPos)
            Else

            End If

            Return sRetVal
        End Get

        Set(value As String)
            Dim nIndex As Integer

            Key = Trim(Key)

            If Key = "" Then
                Err.Raise(INIFILE_ERR_KEY_REQUIRED, , "Missing key parameter")
            End If

            nIndex = _GetIndex(Key)

            If nIndex = -1 Then     '-- Not found
                '-- Add new. Put key value at the bottom
                m_nItemsCount = m_nItemsCount + 1

                ReDim Preserve m_aItems(m_nItemsCount)

                nIndex = m_nItemsCount - 1
            End If

            m_aItems(nIndex) = Key & "=" & value
        End Set
    End Property

    Private Function _GetIndex(ByVal Key As String) As Int32
        Dim i As Integer
        Dim sSearchKey As String
        Dim nPos As Int32
        Dim semiPos As Int32

        sSearchKey = Key & "="   '-- "key="

        For i = 1 To m_nItemsCount
            nPos = InStr(LTrim(m_aItems(i - 1)), sSearchKey, CompareMethod.Text)
            If nPos = 1 Then
                '-- Return actual array index (zero based array)
                Return (i - 1)
            End If
        Next

        Return -1   '-- Not Found
    End Function
End Class